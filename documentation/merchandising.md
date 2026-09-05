# Merchandising Module — Reference

> **Scope.** *What* the Merchandising surfaces do and *why* they are built this way.
> *Where* code lives and *what* it is called is [`ARCHITECTURE.md`](../ARCHITECTURE.md)'s job, and
> this file links to it rather than restating it — two copies of a decision means one of them is
> silently wrong later ([§14](../ARCHITECTURE.md#14-module-reference-documentation)).

---

## 1. Overview

Merchandising owns the order lifecycle up to the point production begins. Six sub-areas are
planned; **four are built**:

| Sub-area | Status |
| --- | --- |
| Purchase order import & management | ✅ import, list, detail |
| BQS — the buyer's buy plan workbook | ✅ import, list, detail — [§7](#7-bqs-the-buyers-buy-plan-workbook) |
| TNA — the time & action schedule | ✅ read-only board — [§9](#9-tna--when-each-milestone-of-an-order-falls) |
| Document library | ✅ upload, list, detail — [§10](#10-the-document-library) |
| Development tech packs | 🟡 |
| Fabric & accessory booking | 🟡 |

Purchase orders are also the module's two firsts for the application as a whole: the first
**buyer-owned** tables ([§9.2](../ARCHITECTURE.md#92-buyer-scoped-access-control)) and the first
list surface with no `name` column ([§4](#43-the-list-has-no-name-column-and-that-changed-a-shared-test)).

---

## 2. Purchase orders arrive by parsing a document

**There is no create form, and there will not be one.** A purchase order is Walmart's document; it
is imported by uploading that document, and the application reads it. Typing one in by hand would
produce an order that claims to be a buyer's and is not.

That single fact drives most of what follows.

### 2.1 The parser is Walmart-specific, despite its names

`app/Services/Merchandising/PoParser/` reads Walmart's **import purchase-order** template and
nothing else. It recognises a page by its banner:

```text
Purchase Order: 5800547464                     WAL-MART CANADA CORP.        Page: 1
```

`RegexLibrary::PAGE_ANCHOR` demands exactly that shape — ten digits, then `Page: <n>`. Handed any
other document it finds no pages and refuses the upload with a message saying so.

**A second buyer's template is a second parser, not a wider regex.** The general class names
(`ParserService`, `PageHeaderExtractor`) describe the *pipeline*, which is reusable; the patterns
inside are not. Widening `PAGE_ANCHOR` to admit another buyer's layout would make every extractor
downstream read the wrong columns and report success.

This is also why the buyer is **chosen on the upload form** rather than inferred from the document
— see [§3.1](#31-the-buyer-is-picked-not-inferred).

### 2.2 Why the engine is allowed to nest

The parser is 33 classes across six directories, three levels under `app/Services/`, where
[§4](../ARCHITECTURE.md#4-the-organizing-rule) allows one. That is a recorded exception with a
stated test: *the nested tree has one entry point and its internals are referenced from nowhere
outside it.* `PurchaseOrderImportService` calls `ParserService::parse()` and nothing else in the
application knows the tree exists.

Flattening produces `PoParserLineItemHeaderExtractor.php` thirty-three times — the same hierarchy,
moved into the filenames.

**The size vocabulary is passed in, and that is why.** Sizes now come from the buyer's BQS
(`Merchandising\BqsSizeVocabulary`, [§2.5](#25-colour-and-size-are-read-from-the-packs-own-columns)),
which is a Merchandising service reading Merchandising models. Resolving it *inside* an extractor
would give the tree an outward dependency and void the exception above, so
`PurchaseOrderImportService` resolves it for the chosen buyer and hands it to
`ParserService::parse()` as a third argument. The engine still knows nothing but its own pipeline.

### 2.3 Three formats, three toolchains, one result

| Format | How it is read | Needs |
| --- | --- | --- |
| `.docx` | `ZipArchive` + `DOMDocument`, in-process | `ext-zip` |
| `.doc`, `.rtf` | LibreOffice converts to `.docx` first | `soffice` |
| `.pdf` | Xpdf `pdftotext -layout` | `pdftotext` |

`tests/Feature/Merchandising/PoParserTest.php` runs the same purchase order through all three and
asserts the **same** 3 orders × 4 packs × 5 line items, and the same template fingerprint. Three
unrelated toolchains agreeing is what makes the extraction credible rather than merely
self-consistent.

**`pdftotext` must be the Xpdf build, not Poppler.** Both answer to that name, both accept
`-layout`, and their column spacing is not byte-identical — and every extractor here slices by
column position. `pdftotext -v` names the implementation. See
[`deployment.md`](deployment.md#21-purchase-order-document-parsing).

### 2.4 Column position is the only structure

The document is a fixed-width text report. There is no markup, so:

- `LineNormalizer` strips **trailing** whitespace and never leading — the left edge is the layout.
- `DocxTextExtractor` expands each `<w:tab/>` to the next multiple of
  `po-parser.parsing.default_tab_stop`. Get that arithmetic wrong and every column shifts while the
  parse still "succeeds", returning the wrong cells.
- `ColumnDetector` reads column starts from the document's own dot guide, in **bytes**, so the
  layout is discovered rather than hard-coded.

This is why the committed fixtures are **redacted with same-length replacements** — see
[§5.1](#51-the-fixtures-are-redacted-and-that-is-load-bearing).

### 2.5 Colour and size are read from the pack's own columns

Every pack states its layout in the heading above its rows, and **the columns differ from pack to
pack within one document**:

```text
COLOR           SIZE             ITEM SALES CHAN      QUANTITY     ITEM NBR
0               16               33                   51           64
GRAY-SIRO MIX   3-6M             OMNI CHANNEL IT             2     050087781

COLOR                            ITEM SALES CHAN      QUANTITY     ITEM NBR     <- a single-item pack
GRAY-SIRO MIX                    OMNI CHANNEL IT             2     050087778
```

`LineItemRowExtractor` reads each field from the span between its own heading and the next one.
Three consequences are deliberate:

- **A pack with no `SIZE` column is read, not guessed at.** `RegexLibrary::LINE_ITEM_COLUMNS` admits
  a heading without one.
- **`ITEM SALES CHAN` is never folded into the size.** A span that ended at `QUANTITY` would take
  `OMNI CHANNEL IT` with it.
- **Colour is read independently of size.** A size that cannot be read costs the size and nothing
  else.

**A size vocabulary is only the fallback**, for a row whose heading could not be read. It comes from
the buyer's own BQS — `bqs_row_pack_sizes.size_label`, the band that is stored as rows precisely
because those headers are data ([§7](#7-bqs-the-buyers-buy-plan-workbook)) — and falls back again to
`po-parser.parsing.size_vocab` when the buyer has no BQS yet. `App\Support\SizeLabel` normalises for
comparison only, because the two documents spell one size two ways (`XS(4/5)` against `XS-4-5`);
each side still stores what its own file said.

The order matters. The BQS band contains bare `S`, `M` and `L`, and a vocabulary consulted *first*,
unanchored, finds the `S` inside `RED-JESTER RED` and reports the colour as `RED-JE`. The fallback
match is therefore anchored to a column boundary, and `PoParserSingleItemPackTest` pins it.

> **Four defects met on one document**, and only the last was visible. A `/^COLOR\s+SIZE/`
> transition meant a single-item pack never opened a `LineItemRows` section; because
> `PurchaseOrderBuilder::buildPacks()` pairs cost blocks to row blocks **by position**, every later
> pack silently took the *next* pack's line items and the last pack was left empty. The colour was
> derived from the size's offset, so an infant size run absent from the config vocabulary produced
> `color = null` on every line — unlinkable to any BQS row, and unreported. V5 then warned five
> times about pack sizes that were correct. The parse said `needs_review` about the one thing that
> was not wrong.

### 2.6 Absence is data; the validator decides what matters

Extractors return `null` for a label that is not printed and never throw. A Walmart order leaves
whole blocks blank routinely — the misc-comments block, the secondary beneficiary, the MFG stock
number column — so a missing field is ordinary.

Whether an absence matters is decided **once**, in `PoDataValidator`, which raises warnings with a
severity. That severity is the whole mechanism: an `Error` fails the parse, a `Warning` only erodes
a confidence score compared against `po-parser.parsing.warn_threshold`.

| Rule | Checks | Severity |
| --- | --- | --- |
| V1 | PO number is ten digits | Error |
| V2 | Quote ID present | Error |
| V3 | Master cartons equals the sum of pack cartons | Warning |
| V5 | Every pack holds the line items its assortment implies | Warning |
| V12 | Two tariff entries (vendor + Walmart) | Warning |
| V13 | Every line item's colour was readable | Warning |
| V14 | A pack printed a size for at least one line | Warning |

V1–V12 are the document's own numbering. **V13 and V14 are this application's**, added for
absences the document has no rule about.

> **V5 previously read "every pack has five line items", as a constant.** That is only true of an
> *assortment* pack. An order of `Single Item Pack` packs holds one line each, and the constant
> raised a warning on every pack of every such order — five warnings is a confidence of 0.75,
> under `warn_threshold`, so a clean parse graded `needs_review` for nothing. The expected count now
> comes from the pack's own `Assortment Ind:` line.
>
> V13 exists because the opposite failure was silent. A line whose colour did not parse cannot match
> a BQS row and cannot be mapped by hand either ([§7](#7-bqs-the-buyers-buy-plan-workbook)), so it
> reports as unplanned — indistinguishable from a colourway the buyer never bought. Every line of
> every infant purchase order was in that state, and the import said `success`.

---

## 3. Importing

### 3.1 The buyer is picked, not inferred

The upload form carries a `buyer_id`, and `PurchaseOrderImportRequest` validates it against **the
uploader's own accessible, active buyers** — not merely `exists:buyers,id`.

That is not defensive coding, it closes a specific failure. An import into a buyer the uploader
cannot see would succeed, and `BuyerScope` would hide the result the instant the redirect landed: a
success toast over an empty table. It reads as a bug and is not one. Restricting the rule to the
same set the picker offers makes the state unreachable, and
`PurchaseOrderImportTest` pins it.

Inactive buyers are not offered, per [§9.3.1](../ARCHITECTURE.md#931-activeinactive-status) —
deactivating retires a buyer from the pickers while leaving its existing orders alone.

### 3.2 Parsing runs inside the request

A queued import with a status page to poll was weighed and **declined for the first version**. It
costs a table, a polling surface, and a hard dependency on a worker being up — and a worker that is
down turns every import into a silent hang, which is a worse failure than a slow request.

What bounds the request instead:

| Bound | Where | Enforced |
| --- | --- | --- |
| File size | `po-parser.limits.max_file_size_kb` | Form request, before any work |
| Pages | `po-parser.limits.max_pages` | After splitting, before extraction |
| Orders per file | `po-parser.limits.max_pos_per_file` | After grouping, before extraction |
| Converter runtime | `po-parser.{libreoffice,pdftotext}.timeout` | `Symfony\Process` |

**Revisit this with evidence, not from memory.** The trigger is a real document timing out, not a
feeling that synchronous is unfashionable. If it changes, `ParsePoDocumentJob` does not exist yet —
it would be new, and `documentation/deployment.md` would owe a section on keeping the worker alive.

### 3.3 Revisions are keyed on the document, not on a counter

Walmart reissues orders and the document says so itself:

```text
Revised Date: 07/06/2026 20:35:01 By: AUTOQP
```

Two columns carry the identity:

- **`source_hash`** — a SHA-256 of the parsed order's payload. Unique per `(buyer_id, po_number)`,
  so re-uploading the *same file* is refused rather than becoming an identical "revision 2". Nothing
  changed, so nobody is asked about it — it is skipped silently and reported in the toast.
- **`revision_no`** — increments for content that genuinely differs. Unique per
  `(buyer_id, po_number)`.

**A revision is confirmed, not assumed.** Content that differs is *not* stored as revision 2 on its
own authority. A genuine Walmart reissue and someone re-uploading a stale document are identical to
the parser — same order number, different bytes — and only the person holding the file knows which
it is. Those orders are staged and answered; see [§3.5](#35-a-collision-is-a-question-not-a-rule).

`revised_at` alone cannot carry this. It is nullable, and both MySQL and SQLite permit repeated
NULLs in a unique index — the same behaviour `create_buyers_table` relies on for `code`. An order
printed without a revision date would then duplicate freely.

`is_current` is maintained inside the import transaction rather than derived from
`max(revision_no)`. It is derivable; the list reads it on every request and a window function per
row is not worth the purity.

**Known limitation.** The hash is of the *parsed* content, so a document Walmart re-exports with a
cosmetic change but the same revision date lands as a new revision. The alternative — trusting
`revised_at` — fails in the other direction and fails silently.

### 3.4 A partly-duplicate document imports the rest

One file holds several orders. If the second of three is already held, the other two still land and
the toast names what was skipped. Refusing all three to protect nothing loses two good orders.

The same holds for collisions: orders that match nothing are written during the upload, and only
the ones that collide wait for an answer.

The severities follow [§8.8](../ARCHITECTURE.md#88-toasts-carry-severity-and-they-clear-themselves)
exactly:

| Outcome | Type | Because |
| --- | --- | --- |
| Everything imported | `success` | — |
| Some already held, or waiting on a decision | `warning` | The actor can clear it themselves |
| Nothing imported, all held | `warning` | Same |
| Unreadable file, no Walmart pages, missing converter | `error` | No work by the actor lifts it |
| Every conflict skipped | `info` | Something finished that the actor did not ask for |
| Anything overwritten | `warning` | Data was destroyed and the message has to say so |

### 3.5 A collision is a question, not a rule

An order arriving under a number already on file, with different content, is **staged** rather than
written. The uploader answers one of three per order:

| Answer | What happens |
| --- | --- |
| **Skip** (the default) | Nothing. The held order is untouched |
| **Revise** | Stored as `max(revision_no) + 1`; the held order keeps its content and loses `is_current` |
| **Overwrite** | The *current* revision is replaced in place. `revision_no` does not move |

**Skip is pre-selected on every row**, so confirming without reading changes nothing that already
exists. Cancelling sends no decisions at all, which is the same thing — the discard path and the
all-skip path are deliberately one code path on the server.

**Overwrite requires `merchandising.purchase-orders.delete`** on top of `import`. Destroying a
stored order is a different power from adding one, the same split that keeps
`admin.users.assign-roles` apart from `admin.users.update`. Without it the option is not rendered
at all, and `PurchaseOrderResolveRequest::authorize()` refuses a hand-made request.

**Overwrite touches only the current revision.** Revisions 1 and 2 survive an overwrite of revision
3, and `revision_no` stays where it is — the count must never lie about how many times Walmart
reissued the order.

#### The hazard overwrite creates

> Overwriting discards the superseded `source_hash`. Re-uploading the **original** document
> afterwards is no longer recognised as already imported and presents as a fresh conflict. This
> cannot be mitigated while still replacing the row, and it is the price of the instruction.

#### Why the rows are staged and not the parse

A document holds up to fifty orders (`po-parser.limits.max_pos_per_file`), so fifty questions cannot
be asked inside one `POST`. What survives to the second request is the **insertable row**, on
`po_imports.staged_orders`:

- Re-parsing on confirm was the simplest code and was declined — it makes the user wait through
  LibreOffice a second time on every import that collides.
- Rehydrating the parse from `po_imports.payload` needs a `fromArray()` on each of the nineteen
  DTOs, written to serve one flow.

That makes one rule load-bearing: `PurchaseOrderImportService::orderAttributes()` returns **scalars
and arrays only** — `po_type` is reduced to its backing value, dates stay strings — so a row that
has been through JSON is identical to one that has not. `PurchaseOrderResolveTest` pins it by
running the same document down both paths and comparing the results column by column.

**Pending is `staged_orders IS NOT NULL`.** There is no companion status column: that is the whole
of the state and a second field would only be a way for the two to disagree.

**An unanswered import survives.** Closing the dialog writes nothing, and the list offers it back —
naming the file and the count — until it is answered. Only the uploader sees it and only the
uploader may answer: buyer scope already hides another buyer's import, and a colleague deciding
"reissue or stale?" about a document they have not seen is not a decision, it is a guess. Only the
latest pending import is offered; a second upload replaces it.

---

## 4. What is stored

### 4.1 Header in columns, the rest in JSON, line items in rows

A purchase-order document has roughly thirteen sections. The split between them is the module's
central decision:

| Table | Holds | Why |
| --- | --- | --- |
| `po_imports` | The file, the complete parse result, every warning | Makes a failed order diagnosable |
| `purchase_orders` | The header as ~30 columns, everything else as `payload` JSON | §8.6 needs real columns to filter and sort on |
| `po_line_items` | Colour, size, quantity, identifiers | Production computes consumption from these |

**Why the header is columns.** `Listable::FILTERABLE` addresses columns, and a JSON path cannot be
filtered or sorted portably across MySQL and SQLite — so a JSON-only order could not have the list
surface [§8.6](../ARCHITECTURE.md#86-every-list-is-paginated-sortable-and-filtered-per-column)
requires.

**Why line items are rows and packs are not.** [§5](../ARCHITECTURE.md#5-module-registry) makes
Merchandising the upstream source of consumption data that Production reads, and consumption is
quantity × colour × size. Those must be joinable. A pack, by contrast, carries no fact beyond its
identifiers and a cost stack nothing queries — so pack identity is denormalised onto each line and
there is no `po_packs` table. The packs are still in `payload` in full.

**Why the rest is JSON.** Addresses, logistics, tariffs and the comment blocks are display-only and
have open field sets. Normalising them means guessing a schema for data no query touches — the same
reasoning [§6.3](../ARCHITECTURE.md#63-migrations) applies to indexes, one level up.

### 4.2 Indexing

Per [§6.3](../ARCHITECTURE.md#63-migrations), and stated so the next change does not re-derive it:

- The two unique constraints on `purchase_orders` **are** indexes. Nothing duplicates them.
- `buyer_id` **leads both**, and is exactly what `BuyerScope` filters on — so the scoped list seeks
  rather than scans, with no index added for it.
- `buyer_id`, `po_import_id` and `purchase_order_id` are `constrained()`; InnoDB indexes a foreign
  key automatically and SQLite does not, so adding one explicitly would be a duplicate on the
  database that matters.
- `is_current` (two values) and `parse_status` (three) are **deliberately unindexed**. Both are
  applied as residual filters behind the buyer predicate, and neither is selective enough to beat a
  scan on its own.
- `po_line_items` has no index beyond its foreign key, because nothing filters it yet. The first
  report that groups by colour or size adds the index its own `EXPLAIN` calls for — and records the
  reasoning here.

### 4.3 The list has no `name` column, and that changed a shared test

`tests/Feature/ListBehaviourTest.php` drove its shared cases against a hard-coded `name` column,
which held while every surface had one. A purchase order is identified by its number, and the
column worth finding mid-string is the vendor.

The dataset now names both per surface — `po_number` for sorting, `vendor_name` for the contains
case. That is the file working as intended: a new list joins `surfaces()` and inherits the whole
contract, and where it genuinely differs, the *contract* is parameterised rather than the surface
excused from it.

Its seed callables also grant the acting user access to the buyer they create. Without that,
`BuyerScope` filters every row away and eight shared cases fail on an empty list — which reads as a
pagination bug.

### 4.4 Failed orders are stored, and that is a hazard with a name

Every parsed order is persisted, `Failed` included, so its warnings stay beside the document that
produced them. The cost is a table holding rows that are known to be wrong.

**The mitigation is `PurchaseOrder::scopeUsable()`.** Production and Reports must read through it
rather than remembering a condition. The list defaults to `current()->usable()`, and the failures
are reachable only through the explicit `?view=failed` tab.

If you are reading this because you are about to join `purchase_orders` to something: use
`->usable()`, or say in a comment why you want the failures too.

### 4.5 The template fingerprint is a drift detector

`template_fingerprint` is a hash of the ordered set of *sections* the state machine recognised — not
of any value inside them. Every order printed from the same Walmart template fingerprints
identically, whatever it says.

A fingerprint nobody has seen before means the **template** moved. That matters because the failure
mode of a template change is not an exception: the extractors keep running and quietly return less.
The fingerprint is the cheapest available signal that this has happened, and
`PoParserTest` asserts all three formats agree on it.

---

## 5. Testing

| File | Covers |
| --- | --- |
| `PoParserTest` | All three formats against the redacted fixtures — counts, fields, fingerprint, date order, refusals |
| `PurchaseOrderImportTest` | Permissions, buyer access, persistence, staging, duplicates, actor stamping, retention, toast severities, the view tabs |
| `PurchaseOrderResolveTest` | The three decisions, the `delete` gate on overwrite, the JSON round trip, and who may answer |
| `PurchaseOrderScopeTest` | `BuyerScoped` on the first real buyer-owned tables, including the `po_line_items` limit |
| `ListBehaviourTest` | The whole §8.6 contract, inherited |

### 5.1 The fixtures are redacted, and that is load-bearing

`tests/Fixtures/Merchandising/PO-SAMPLE-WALMART.{docx,doc,pdf}` are a real Walmart purchase order
with every company name, personal name, address, identifier and cost replaced.

**Every replacement is exactly the same length as what it replaced.** The parser slices by column
position, so a shorter vendor name shifts every cell to its right and the fixture stops exercising
the layout it exists to prove. Currency amounts were scrambled digit-wise, with tariff numbers
(`6104.63.00.00`) and integer quantities excluded — validation rules V3, V5 and V12 compare those,
and changing them would invalidate the assertions.

If you regenerate the fixtures, verify afterwards that all three formats still yield 3 orders ×
4 packs × 5 line items and the same fingerprint. If they do not, the redaction changed the layout.

### 5.2 Nothing skips when a binary is missing

The demo this was ported from called `markTestSkipped` when `pdftotext` was absent. The consequence
was a repository whose only test silently passed without running anything.

LibreOffice and Xpdf are documented prerequisites. If one is missing these tests **fail**, which is
the correct outcome and the reason the deployment runbook lists them.

### 5.3 `mfgStockNumber` is null, and that is correct

Every line item parses with a null MFG stock number. That is not a missed capture — Walmart leaves
the column blank on this order:

```text
LTBLUE-BALLAD B XS-4-5        3   051156416                    0000024640803   0821729901696
```

The assertion list in `PoParserTest` deliberately omits it. Asserting it populated would assert a
fact about the document that is not true.

---

## 6. How to extend

### Adding a second buyer's template

1. A **new** extractor set under `app/Services/Merchandising/PoParser/`, with its own page anchor.
   Do not widen `RegexLibrary::PAGE_ANCHOR`.
2. Dispatch on the buyer chosen on the form — which is why it is chosen rather than inferred.
3. A redacted fixture per format, verified as in [§5.1](#51-the-fixtures-are-redacted-and-that-is-load-bearing).

### Promoting a payload field to a column

1. A migration adding the column, flat and chronological.
2. Map it in `PurchaseOrderImportService::storePurchaseOrder()`.
3. **Backfill existing rows out of `payload`** — the data is already there, which is the advantage
   of having kept it.
4. Add it to `FILTERABLE`/`SORTABLE` only if a query actually uses it, and record the `EXPLAIN`
   reasoning in [§4.2](#42-indexing).

### Making imports asynchronous

Read [§3.2](#32-parsing-runs-inside-the-request) first, then bring the measurement that justifies it.

---

## 7. BQS — the buyer's buy plan workbook

> **A BQS here is not a "budget quotation sheet".** `ARCHITECTURE.md` §5 said so until this was
> built, and it was wrong; the line is corrected there rather than annotated. A BQS is the buy
> plan **George/Walmart sends us**: one row per vendor style per colourway, carrying store /
> ecomm / omni quantities, a cost stack, pack structure, and month-by-month DC intake. Nothing in
> it is prepared by this company. The reference file is
> `BQS GR4064 SKATER DRESS ORDER CONFIRMATION .xlsx`, kept redacted-free as a fixture because it
> contains no personal data beyond a buyer's merchant name.

### 7.1 Twenty-eight of the eighty-nine columns are data, not schema

The workbook is `A`–`CK` with **two header rows**: row 1 is merged group bands, row 2 is leaf
headers, data starts at row 3.

| Band (row 1) | Leaf columns (row 2) | Count |
| --- | --- | --- |
| *(none)* | FYE … Reg Ecom Penetration Percent | 33 |
| Number of stores / Initial Set Units Per Store | Total Stores, Extra Initial Packs | 2 |
| Initial Set Units, Total BUY Units, Replenishment Units, First Cost, Landed Store Cost, Total Buy Dollar | Store, Ecomm, OMNI × 6 | 18 |
| Pack Details | Commodity Type … Whse Pack | 8 |
| **Break Packs / Case Packs** | `XS(4/5) … XL(14/16)` × 2 | **10** |
| **In DC Units** | `November-2026 … April-2028` | **18** |

The last two bands are headed with **values**. A different season carries different months; a
different garment carries different sizes. As columns they would need an `ALTER TABLE` per
upload, so they are rows in `bqs_row_months` and `bqs_row_pack_sizes`.

That also keeps sizes joinable to `po_line_items`, which stores colour and size as rows for the
same reason ([§4.1](#41-header-in-columns-the-rest-in-json-line-items-in-rows)). Had the BQS
stored sizes as columns, no report could ever have matched a plan line to the order realising it.

**The remaining 61 are columns, not JSON.** Unlike a purchase order, a BQS has no display-only
sections — every field is a number someone will eventually want to filter, sort or sum. The
JSON-payload trade that [§4.1](#41-header-in-columns-the-rest-in-json-line-items-in-rows) makes
would buy nothing here and cost `FILTERABLE`.

### 7.2 The header takes two rows to read, and merges are the authority

`Store`, `Ecomm` and `OMNI` each appear **six times** in row 2. Only the row-1 band tells them
apart, so every such column is named `{band}_{leaf}` — `initial_set_units_store`,
`first_cost_ecomm`. `BqsHeaderMap::STATIC_COLUMNS` is that mapping.

A band **is** a merged cell: `AI1:AK1` really is one cell spanning three columns, and
PhpSpreadsheet returns its label only in the top-left. `BqsHeaderMap::resolveBands()` therefore
reads the sheet's merge ranges to get each band's exact extent.

> **This replaced a carry-forward heuristic** that repeated the last non-empty label rightwards
> until the next one. That is right in the middle of a band and wrong after the last: a column
> added to the right of `In DC Units` inherited that band and was read as a malformed month
> instead of being reported as unrecognised. Merge ranges say where a band stops; guessing does
> not.
>
> The consequence is that **`setReadDataOnly(true)` cannot be used**, because it discards merge
> geometry along with the styles. With it on, `AI1:AK1` collapses to a label on `AI` alone,
> `Ecomm` and `OMNI` map to nothing, and seventeen of the eighteen month columns disappear — and
> the import still *succeeds*, silently, with most of the workbook missing. That failure was
> caught by a test asserting 108 month rows and getting 6.

Columns are matched **by name in any order**. Positional reading was considered and rejected: one
inserted column would shift 89 fields and write wrong data into every row with no error at all.

A missing column from `BqsHeaderMap::REQUIRED_COLUMNS` refuses the file by name; anything else
unrecognised is imported with a warning. The required list is deliberately short — the row key's
seven components plus the one quantity that makes a BQS a buy plan. George trimming a column
nothing keys on is not a reason to stop importing.

### 7.3 A BQS has no identity, so it is derived from its rows

This is the module's second central decision, and the one with no precedent to copy.

A purchase order carries its own ten-digit number, so a reissue is obvious
([§3.3](#33-revisions-are-keyed-on-the-document-not-on-a-counter)). **A BQS workbook carries
nothing**: no document number, no revision date, and a `Quote ID` column blank in every file
received. The owner confirmed it is always blank.

The options were weighed and three were rejected:

| Candidate | Why not |
| --- | --- |
| `FYE + Season + Department + Fine Line` | A fine line is a merchandise classification, not a program. Two unrelated buys in one season collide, and the second silently supersedes the first — data loss with no trace. |
| A program number parsed out of the style (`4064` from `GRS74064GX`) | Needs a fixed rule for extracting it that nobody could state. |
| Ask the uploader to name the BQS | A third field on the form, and a typo makes a revision into a new record. |

What was chosen instead:

> **Two uploads are the same BQS when their sets of `bqs_rows.row_key` intersect.**

`row_key` is a sha256 over seven normalised components the owner named — FYE, season, department,
vendor style, pantone colour, colour variant, item description — each **also stored as an
ordinary column**, so the key is reproducible from the row rather than an opaque digest.
`BqsRowKey::COMPONENTS` is the definition, and its order is a contract: change it and every stored
key stops matching, which reads as every BQS suddenly being new.

This invents no identifier, so it cannot invent a wrong one; unrelated buys have disjoint row keys
and never collide; and the answer to "which held BQS does this overlap" is one question per
workbook rather than one per row.

**A workbook overlapping two held revisions is refused**, not guessed at. It is a revision of
neither, and picking one would silently orphan the other.

### 7.4 One decision per workbook, not one per row

The purchase-order dialog stages up to fifty conflicts and asks about each
([§3.5](#35-a-collision-is-a-question-not-a-rule)). Copying that here would produce a 200-decision
form for a 200-row BQS. A workbook *is* one BQS, so **skip / revise / overwrite** is asked once.

`BqsConflictDecision` is a separate enum from `PoConflictDecision` despite identical values,
because what is being decided is not the same thing and a shared docblock could only describe one
of them. If a third importer ever wants these words, that is when one `ImportConflictDecision`
earns its place — not before.

`overwrite` destroys a revision and cascades to its rows, months and pack sizes, so it needs
`merchandising.bqs.delete` on top of `import` — the same split
[§3.5](#35-a-collision-is-a-question-not-a-rule) makes.

### 7.5 Revisions chain through `root_id`

Revision 1 points at **itself**, written in a second statement inside the import transaction
because the id does not exist until the insert returns.

Two simpler shapes were rejected:

- **`unique(buyer_id, title, revision_no)`** — `title` is the workbook's file name, and a reissue
  routinely arrives under a different one.
- **`root_id` null on revision 1** — both MySQL and SQLite permit repeated NULLs in a unique
  index, so the constraint would not bind the revision that needs it most. The same trap
  `create_purchase_orders_table` records for `revised_at`.

`cascadeOnDelete` on `root_id` is safe rather than dangerous: **overwrite** only ever deletes the
*current* sheet, and the root is current only when it is the sole revision. `BqsImportService::overwrite()`
still detaches the children first, because the cascade is not obvious to the next reader.

### 7.6 Two facts are entered, not read

The upload form asks for the buyer, the **BQS date**, and the file.

The buyer is picked for the reason [§3.1](#31-the-buyer-is-picked-not-inferred) gives. The date is
picked because **the workbook has no date of any kind** — and a file's own timestamp is the date
it was last copied between machines, which is not the same fact. It is required, held on
`bqs_imports` as well as `bqs_sheets` so a staged upload remembers it until the collision is
answered, and each revision keeps the date entered with its own upload.

### 7.7 Fidelity over correction

`OMNI = Store + Ecomm` and `Total BUY = Initial + Replen` hold in every row of every file seen so
far. They are **checked and not enforced**: where a row disagrees with itself, the buyer's values
are stored unchanged and a warning is raised against that row.

The workbook is the source of truth. Silently recomputing it would make the application disagree
with the document it claims to hold, and nobody would find out until a costing did.

Related type care, all of it load-bearing:

- **Money is `decimal` and stays a string** end to end. Excel hands over `70711.199999999997`; a
  float cast anywhere — including in the React detail page — puts that back.
- **Identifiers are strings even when numeric.** `colour_variant` (`503441`), `fine_line`,
  `vendor_no`, `season_code` are codes, and leading zeros matter.
- **`pack_ratio` looks like a ratio and is a label** (`"FYE28 OPP Dress"`).
- **`regular_imu_pct` is stored as sent** (`55`, not `0.55`).
- **`wm_wk_in_store` is a composite** the buyer jams into one cell — `"3 (2027-02-13)"`. The raw
  string is kept for fidelity and both halves parsed out beside it.
- **`AL1` and `AL2` disagree** in the source file: the band reads *"Initial Set Units Per Store"*,
  the leaf reads *"Extra Initial Packs"*. The leaf wins, because it is what the values are.

### 7.8 Column D is a person

`Buyer` in the workbook is `JELENA PAPAGEORGE` — the buyer's own merchant. It is stored as
`bqs_rows.buyer_merchant` and is **never** the `buyers` foreign key, which lives on the parent
sheet. Conflating the two would put a person's name where
[§9.2](../ARCHITECTURE.md#92-buyer-scoped-access-control) expects a scope key.

### 7.9 Scoping goes two levels deep

`bqs_imports` and `bqs_sheets` are buyer-owned and carry the trait. `bqs_rows` reaches its buyer
through the sheet; `bqs_row_months` and `bqs_row_pack_sizes` reach it through the row. None of the
three has a `buyer_id`, per §9.2's rule against a scope that joins — a two-hop parent is still a
parent. `BqsScopeTest` proves the case `PurchaseOrderScopeTest` does not.

### 7.10 Testing

The real workbook is the fixture wherever the question is "does this work on what the buyer
actually sends". Everything about the **dynamic** bands is proved against workbooks built in the
test with PhpSpreadsheet's writer, because proving "any month range loads with no migration"
needs a *second* range — and a second binary fixture would hide what differs between them.

Those synthetic workbooks **merge their band cells**, because the reader treats merge ranges as
the band's extent. A test workbook that wrote the label into one cell and left the rest blank
would not be the file George sends, and would pass while the real one failed.

### 7.11 How to extend

**A second buyer's BQS layout.** Add its `{band}|{leaf}` pairs to `BqsHeaderMap::STATIC_COLUMNS`
if the fields are the same under different names; if the *shape* differs, dispatch on the buyer
chosen on the form, as [§7 of the parser guidance](#adding-a-second-buyers-template) does. Do not
loosen `REQUIRED_COLUMNS` to make one file fit.

**A report joining BQS to purchase orders.** `bqs_rows` is already indexed on
`(bqs_sheet_id, vendor_style_no)` for exactly this. The matching rule — exact style, or style plus
colour — is deliberately not decided, because no report has asked yet.

**Reading the workbook without loading styles.** Read [§7.2](#72-the-header-takes-two-rows-to-read-and-merges-are-the-authority)
first. The fix is a second cheap pass for the merge ranges, never `setReadDataOnly(true)`.

---

## 8. Linking purchase orders to the BQS rows that planned them

A BQS row is what the buyer *planned*; a purchase-order line is what they *ordered*.
`po_line_items.bqs_row_id` joins them, and `Merchandising\BqsPoLinker` is its only writer.

### 8.1 The colour field is not a colour

A Walmart PO states colour as `{family}-{pantone}` in a **15-character** column. Every distinct
value in the reference document:

| PO `color` | length | BQS family | BQS pantone |
| --- | --- | --- | --- |
| `LTBLUE-BALLAD B` | 15 | `LTBLUE` | `BALLAD BLUE` — **truncated** |
| `NATURL-SANDSHEL` | 15 | `NATURL` | `SANDSHELL` — **truncated** |
| `PINK-CANDY PINK` | 15 | `PINK` | `CANDY PINK` — fits exactly |
| `TEAL-ICY MORN` | 13 | — | *no BQS row exists* |

So `color == pantone_colour` matches nothing at all. `Merchandising\BqsColourMatch` is the only
place that string is parsed.

### 8.2 Strict equality, and what it costs

**The owner chose strict equality on both halves, having been shown that truncation makes
`BALLAD BLUE` and `SANDSHELL` permanently unmatchable.** Only `PINK-CANDY PINK` auto-links out of
four; the rest reach a person. `BqsPoLinkTest` pins both non-matches so that widening the rule to
a prefix match fails loudly rather than quietly linking more than was agreed.

Its consequence shaped the design. If a manual decision were a fact about a line item, it would be
re-made on every future order — so it is stored as a **rule** in `bqs_colour_links`, mapping
(buyer, style, PO colour) → BQS **row key**. The next order carrying that colour resolves with no
second visit. The picker also sorts likely candidates first; that is ordering only and never
creates a link.

`TEAL-ICY MORN` is the case the manual path is *not* for: there is no BQS row, and unlinked is the
correct permanent answer. The picker is restricted to the same style and buyer so nobody can
attach it to an unrelated row and manufacture a fact Production would later read.

### 8.3 Revisions, and the ordering trap

BQS revisions write new rows; PO revisions write new lines. Links are carried across by `row_key`:

- **revise** — after the new rows exist, links are re-pointed from the old ones.
- **overwrite** — links are **captured before the delete**. `bqs_row_id` is `nullOnDelete`, so
  deleting first erases every link and the only symptom is a BQS reporting nothing ordered. Both
  orderings are tested.
- **PO revision** — the new lines resolve through auto-matching plus the colour rules, so manual
  decisions reappear with no extra machinery.

### 8.4 Ordered units, and the channel that took two attempts

**`po_line_items.quantity` is the size ratio inside one pack.** The five sizes read 3, 4, 4, 2, 1 —
the fourteen of "14PC GR SS SKATER DRESS" — and `total_cartons_per_line` is how many packs were
ordered. `PoLineItem::orderedUnits()` multiplies them; the multiplier is denormalised onto the line
for the same reason `vendor_stock` is.

The three orders in the reference document reconcile to the plan exactly:

```text
PO …001 (type 43)   5,502  = Initial Set Units / Store
PO …002 (type 43)     266  = Initial Set Units / Ecomm
                   -------
                    5,768  = Initial Set Units / OMNI
PO …003 (type 42)  21,868  = Replenishment Units / OMNI
```

> **The comparison was first built against Store, and that was wrong.** The error came from
> reading one pack's carton count (393) as if it applied to the whole order; across the document
> they range from 16 to 1,562. Ecomm turns out to be ordered as its own purchase order, so the
> initial buy is two type-43 orders summing to OMNI. Against Store, an exactly-complete initial buy
> reads 105%. The line is corrected rather than annotated, and a test now asserts that carton
> counts differ within one import so the assumption cannot be made again silently.

Which half of the plan an order satisfies comes from matching `purchase_orders.po_type` against the
codes **the BQS row itself names** — nothing about Walmart's numbering is hard-coded.

### 8.5 The guard that the database does not provide

Neither `po_line_items` nor `bqs_rows` carries a `buyer_id`; both reach their buyer through a
parent ([§9.2](../ARCHITECTURE.md#92-buyer-scoped-access-control)). Nothing at the database level
prevents a Walmart line pointing at a George row. The whole guard is `BqsPoLinker`'s
buyer-constrained queries plus `BqsLinkRequest`'s validation, and it has its own test. **Any future
relationship between two child tables inherits this problem.**

### 8.6 How to extend

**A quantity comparison per size.** The BQS carries pack ratios per size and the PO carries them
too, in different notations (`XS(4/5)` against `XS-4-5`). Normalising those is the first piece of
work, and no document has yet shown that the two size sets always correspond.

**A second buyer's colour format.** Dispatch inside `BqsColourMatch` on the buyer, the way a second
parser would. Do not loosen the existing rule to accommodate one.

---

## 9. TNA — when each milestone of an order falls

The board at `/merchandising/tna` answers the question the business asks every morning: **is this
order on schedule?** It is the proof-of-concept slice of `Master Order recap.xls`, a 194-column
sheet that answers it by hand today.

`Merchandising\TnaCalculator` is the **only** place any of this arithmetic lives. A second
implementation would drift, and a schedule that disagrees with itself is worse than no schedule.

### 9.1 The chain, and where it breaks

```text
purchase order → its linked BQS rows → one BQS sheet → bqs_date
                                  vendor_ship_date − bqs_date = lead time
                                        → the active tna_templates band covering it
                                              → bqs_date + each offset = the planned dates
```

Every link can fail, and **each failure names itself** in the `reason` on `TnaPlanDto`, which the
page prints under the row:

| Failure | What the reader is told to do |
| --- | --- |
| No line item links to a BQS row | Link a colour on the purchase-order page |
| The order's links reach **two** BQS sheets | Decide which plan it was placed against — refused, not averaged |
| No `vendor_ship_date` | Nothing here; the document did not carry one |
| Lead time ≤ 0 | A data error — milestones would fall after the shipment they precede |
| No active band covers the lead time | Add or widen a band in Settings |

Three blank cells and three blank cells with a sentence are the difference between "add a band in
Settings" and "link a colour on the order", and a reader cannot tell them apart otherwise. That is
why the DTO carries a reason rather than just nulls.

### 9.2 Lead time is the sheet's own formula

`vendor_ship_date − bqs_date`, in whole days. This is not an interpretation: cell `J4` of the recap
sheet is literally `=I4-D4`, shipment date minus BQS/order-received date.

Two related facts from the same sheet, worth knowing before extending this:

- The sheet **derives** shipment as `=H4-79`, in-store date minus 79 days. We take it from the
  purchase order instead, because the order is the document the buyer actually sent. `bqs_rows`
  does carry `wm_wk_in_store_date` if that route is ever wanted.
- Real templates also offset **backwards** from a later milestone (`FP4` is `=ET4-58`), not only
  forwards from the BQS date. The POC only does forwards. A backwards offset needs an anchor
  column on `tna_template_milestones` and is the natural next step.

### 9.3 Why templates match a band

Measured, not preferred. The three orders in the reference data:

```text
PO …001   ship 2026-10-22   BQS 2026-02-01  →  263 days
PO …002   ship 2026-10-23   BQS 2026-02-01  →  264 days
PO …003   ship 2026-10-24   BQS 2026-02-01  →  265 days
```

Ship dates are staggered by a day each, so one BQS produces three different lead times. **An exact
key matches none of them** and would need a row per integer, growing forever. `241–300` covers all
three. The register's own reasoning is in
[`documentation/settings.md §6.1`](settings.md#61-the-band-is-the-key-and-it-is-measured); the test
that pins it is `TnaTest::a single band serves three different lead times`.

### 9.4 A row is an order, not a colour

The recap sheet's grain is PO × style × colour — rows 7–10 are one order in four colourways. The
board's grain is the **order**, because all three POC dates are order-level facts: the ship date is
the order's, and the BQS date is the sheet's. Per-colour rows would repeat identically four times.

That changes when a milestone becomes per-colour, which the sheet says it eventually does — it
carries a required sample quantity and size per colour. At that point the board's grain follows the
milestone, and this is the decision to revisit.

### 9.5 Nothing is stored

There is no TNA table. A plan is derived on every read, so correcting a template corrects every
order at once and there is nothing to backfill or recalculate.

**The trade is that editing a template rewrites the past.** A schedule printed last week is not
reproducible from the data. That is right for a proof of concept and wrong for a system of record —
and the thing that will force the change is capturing *actual* dates beside the planned ones, which
is what the recap sheet does with its Plan/Actual/Status triplets.

### 9.6 The board costs the same whatever the page holds

`TnaCalculator::plans()` takes the whole page: the BQS dates come back in one grouped query and the
register is loaded once and reused, so twenty-five orders cost what one does. `TnaCalculator::plan()`
exists for a single order and is a thin wrapper over it — **do not call it in a loop**, which is
what the query-ratio test in `TnaTest` guards against.

### 9.7 What the list cannot do

Lead time and every planned date are computed per row *after* the query, so the database cannot sort
or filter by them. `TnaIndexRequest` borrows `PurchaseOrder`'s allow-lists, and `vendor_ship_date` is
the sortable column closest to lead time. Sorting by lead time means storing it, which is exactly the
trade §9.5 makes.

### 9.8 How to extend

**A new milestone.** Add a `TnaMilestone` case and give it an offset in the register. No migration,
no TypeScript — the board builds its columns from the server's list, and the template form builds
its inputs from the enum. Only `Shipment` is special, because it is read from the order.

**Actual dates.** The point at which plans must be stored (§9.5). Expect a `tna_plans` table keyed
on the order, written when a plan is first computed, plus a recalculate action and a rule for when
it runs.

**Backwards offsets.** An anchor column on `tna_template_milestones` naming the milestone to count
back from, defaulting to the BQS date. The recap sheet already works this way (§9.2).

---

## 10. The document library

The third upload surface, and the only one that is **not** an importer.

### 10.1 Why it exists

The two importers are parsers, and both are template-specific: `PoParser` reads Walmart's purchase
order and finds nothing in any other document; `BqsWorkbookReader` reads George's buy plan and
refuses a workbook missing a required column. That is correct — a parser that guesses is worse than
one that refuses — but it leaves everything else with nowhere to go, and everything else is most of
what arrives. Size charts, TNA working sheets, a photograph of a swatch, a supplier's `.rtf` quote,
whatever a buyer emails next week.

The library takes all of it and reads none of it.

### 10.2 The one thing a user can get wrong

**`file_type` is a label, not an instruction.** A batch typed `BQS` is a stored document; it writes
no `bqs_sheets` row, produces no revision, and is invisible to the BQS list. Importing a BQS is
still the Import button on the BQS screen.

That overlap is the accepted cost of the separation. The owner chose it over routing `BQS`- and
`Purchase order`-typed uploads into the import services, which would have given the application two
write paths to one fact — the failure mode `ARCHITECTURE.md` §5 records for buyer access, where two
surfaces editing one thing is how they drift apart. The mitigation is wording rather than mechanism:
the upload dialog says "Nothing is read out of them — to import a BQS or a purchase order, use the
Import button on those screens instead", and the list's own description repeats it.

### 10.3 A batch is the unit, and the buyer is optional

Two decisions shape the schema.

**One upload is one batch.** `document_uploads` holds who, when, the type and an optional title;
`document_files` holds one row per file. The index lists batches and the detail page lists files,
because [§8.6](../ARCHITECTURE.md#86-every-list-is-paginated-sortable-and-filtered-per-column)
records that grouped rendering and pagination are incompatible — a batch straddling a page boundary
would be cut in half. `file_count` is stored rather than counted so the list can sort on it.

**`buyer_id` is nullable, and null means everyone.** A size chart or a TNA formula frequently
concerns no single buyer, and the alternative — forcing a buyer — would file such documents under a
buyer they do not belong to, which is worse than filing them under none. The consequence is that
this is the first table needing
[`BuyerScopedOrGlobal`](../ARCHITECTURE.md#92-buyer-scoped-access-control), because the plain scope
gets a null backwards: `whereIn` never matches `NULL`, so an unassigned row would have been visible
to nobody rather than to everyone.

**Neither dialog used to close after a successful save.** `document-upload-dialog.tsx` carried no
`onSuccess` at all, and `document-replace-dialog.tsx` derives its `open` from `file !== null` with
nothing clearing it — so an upload left the panel standing with the file input still populated, one
click away from sending the same batch twice, and a replace offered to replace the file it had just
replaced. Both now follow
[ARCHITECTURE.md §8.10](../ARCHITECTURE.md#810-a-form-modal-has-three-buttons-and-each-one-means-something),
which is also where the standard's clearing mechanism is explained. Worth knowing here because a
**file input is the one control nothing else can clear** — assigning to its `value` is refused by
every browser, so the remount that section prescribes is not a preference on this surface.

Batches arrive in runs, so **upload keeps "Save & add another"**; replace does not, acting on one
named file.

### 10.4 What is deliberately absent

| Not built | Why |
| --- | --- |
| Parsing, text extraction, OCR | The scope is collection. Adding any of it later changes nothing here. |
| Version history on replace | Replacing destroys the old file. It therefore needs `delete` as well as `update`, the same split `BqsResolveRequest` makes for `overwrite`. |
| Deduplication | `file_hash` is stored but **not** unique: the same size chart legitimately arrives twice under two labels. That is the opposite of `bqs_imports`, where a byte-identical re-upload is a no-op worth detecting. |
| A per-file size limit | `upload_max_filesize` and `post_max_size` are the ceiling, by decision. |
| A buyer filter cell | The column is an id; filtering by name needs a join the shared apparatus does not do. The scope narrows the rows already. |

### 10.5 Two limits that are not policies

**Twenty files per batch is PHP's `max_file_uploads`.** Files past it are dropped from `$_FILES`
before any PHP code runs — no warning, no validation error, they simply never arrive. The form
validates against the number purely so the user is told, and the message says "send the rest as a
second batch" because that is the actual remedy. Raising it means editing `php.ini` *and* the config
key; raising the config alone re-opens the silent loss.

**The extension allow-list is a security control.** `svg` and `html` are absent and must stay
absent: the preview route renders allow-listed files inline from the application's own origin, and
anything that can carry script there is stored XSS. The preview response also sends
`X-Content-Type-Options: nosniff`, because the stored MIME type is whatever the uploader's browser
claimed.

### 10.6 The disk

Files live on the private `local` disk under `merchandising-documents/{batch id}/{ULID}.{ext}`, and
are served only by a route that checks the permission and the buyer scope — there is no public URL.

**The uploader's filename never reaches the filesystem.** It is held on the row and restored by the
download response, so a crafted name cannot escape the batch directory, collide with another
batch's file, or be a name the operating system treats specially.

`DocumentLibraryService` is the only writer, and its ordering rule is **write the disk inside the
transaction, delete it after the commit**: a failed batch rolls the rows back and unlinks what it
had already written, and a delete removes the object only once the row is definitely gone. A stored
object with no row is invisible litter; a row with no object is a broken download somebody reports.

### 10.7 Testing

`tests/Feature/Merchandising/DocumentLibraryTest.php` covers the server: permissions per route, the
unassigned-buyer case, the grouped-`OR` regression, the batch cap, scoped bindings, download,
preview headers, replace, and both deletes.

`tests/Browser/DocumentUploadFormTest.php` covers the wire format of the multi-file input — and
stops at the request body, because **the browser plugin cannot carry a multipart request at all**.
See [§13.2](../ARCHITECTURE.md#132-a-dom-level-test-harness-exists--testsbrowser), which records
the limitation and how it presents.

### 10.8 How to extend

**A new document type.** Add a case to `DocumentType`. No migration, no TypeScript — the form, the
filter cell and the list badge all build from `options()`.

**Parsing, if it is ever wanted.** It belongs behind a job reading `document_files.stored_path` and
writing a rendition alongside the row. Nothing about the collection path changes, which is the
property the collect-only decision was chosen to keep.

**Per-file types.** Move `file_type` from `document_uploads` to `document_files`. The batch stays the
upload unit; only the label moves, and the list's type column becomes a summary rather than a value.
