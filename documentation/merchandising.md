# Merchandising Module — Reference

> **Scope.** *What* the Merchandising surfaces do and *why* they are built this way.
> *Where* code lives and *what* it is called is [`ARCHITECTURE.md`](../ARCHITECTURE.md)'s job, and
> this file links to it rather than restating it — two copies of a decision means one of them is
> silently wrong later ([§14](../ARCHITECTURE.md#14-module-reference-documentation)).

---

## 1. Overview

Merchandising owns the order lifecycle up to the point production begins. Four sub-areas are
planned; **one is built**:

| Sub-area | Status |
| --- | --- |
| Purchase order import & management | ✅ import, list, detail |
| Development tech packs | 🟡 |
| BQS (budget quotation sheet) | 🟡 |
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

### 2.5 Absence is data; the validator decides what matters

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
| V5 | Every pack has five line items | Warning |
| V12 | Two tariff entries (vendor + Walmart) | Warning |

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
  so re-uploading the *same file* is refused rather than becoming an identical "revision 2".
- **`revision_no`** — increments for content that genuinely differs. Unique per
  `(buyer_id, po_number)`.

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

The severities follow [§8.8](../ARCHITECTURE.md#88-toasts-carry-severity-and-they-clear-themselves)
exactly:

| Outcome | Type | Because |
| --- | --- | --- |
| Everything imported | `success` | — |
| Some already held | `warning` | The actor can clear it themselves by deleting what is there |
| Nothing imported, all held | `warning` | Same |
| Unreadable file, no Walmart pages, missing converter | `error` | No work by the actor lifts it |

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
| `PurchaseOrderImportTest` | Permissions, buyer access, persistence, revisions, duplicates, actor stamping, retention, toast severities, the view tabs |
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
