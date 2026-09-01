# Settings Module — Reference

> **Scope.** This file explains *what the Settings surfaces do and why they are built this way*.
> Where things live and what they are called is [`ARCHITECTURE.md`](../ARCHITECTURE.md)'s job — this
> file links to it rather than restating it, so a decision never has two copies that can disagree.
>
> Update this file in the same change as any Settings surface it describes.

---

## 1. Overview

Settings is two unrelated things behind one URL prefix, and keeping them apart is the module's main
structural fact.

| Half | What it is | Backend | Pages | Status |
| --- | --- | --- | --- | --- |
| Account settings | The signed-in user editing **their own** profile, security and appearance | `Settings\{Profile,Security,Appearance}Controller` | `pages/settings/{profile,security,appearance}.tsx` | ✅ built |
| Master data | Product/process reference tables **everyone reads and only Settings writes** | `Settings\{NotificationColor,TnaTemplate}Controller` | `pages/settings/master-data/` | 🟡 notification colours and TNA templates built, the rest planned |
| App configuration | App-level toggles and defaults | — | `pages/settings/application/` | ⬜ planned |

They share nothing: not a permission, not a layout, not an audience. The account half is a personal
preferences screen every authenticated user reaches from their own menu; the master-data half is an
administrative surface behind `settings.master-data.*` that shapes data for the whole application.

**Departments and designations are not here.** HR and org-structure reference data is Admin-owned;
only product and process reference data is Settings-owned. The split is by *subject*, not by table
shape, and the reasoning is in
[ARCHITECTURE.md §9.4](../ARCHITECTURE.md#94-master-data).

---

## 2. Account settings

Largely starter-kit code, and saying so is the useful fact — there is little here that a reader
needs warning about.

| Surface | Route names | Notes |
| --- | --- | --- |
| Profile | `profile.edit`, `profile.update`, `profile.destroy` | Name, email, employee fields |
| Security | `security.edit`, `user-password.update` | Behind `RequirePassword`; the password route is throttled at 6/min |
| Appearance | `appearance.edit`, `appearance.update` | Theme, persisted to `users.theme` |

Two things about them are *not* starter-kit and are easy to break:

- **Those route names are deliberately unprefixed.** Fortify and the starter kit's own components
  reference `profile.edit` and `user-password.update` by name. Renaming them to `settings.profile.*`
  breaks auth flows that never mention Settings. Every *new* Settings route uses the `settings.`
  prefix; these four are the grandfathered exception, recorded in
  [ARCHITECTURE.md §5](../ARCHITECTURE.md#5-module-registry).
- **Login is by `employee_id`, not email**, which is why the profile form treats email as an
  ordinary unique field rather than the identity. See
  [ARCHITECTURE.md §9.6](../ARCHITECTURE.md#96-authentication-identity).

### 2.1 `SettingsLayout` is the *account* layout, not the Settings layout

`resources/js/layouts/settings/layout.tsx` renders a fixed Profile/Security/Appearance nav, a
heading that reads "Manage your profile and account settings", and a `max-w-xl` content column
sized for a short form.

`app.tsx` used to map the whole `settings/` page prefix to it. That was wrong — it conflated a URL
prefix with a layout — and it surfaced the moment the first master-data screen was built, because a
paginated table with a filter row cannot render in a `max-w-xl` column at all. The resolver now
matches **three named pages** and everything else under `settings/` falls through to `AppLayout`.

The alternative was teaching `SettingsLayout` a second nav section and a conditional width, which
makes one component serve two unrelated jobs that every future master table then edits. Narrowing
the resolver was the smaller and more reversible half. Full reasoning, and the consequence that a
fourth account page must be added to the list by hand, is in
[ARCHITECTURE.md §8.1](../ARCHITECTURE.md#81-layout-resolution--resourcesjsapptsx).

---

## 3. Master data — notification colours

The first master-data surface, and the template the rest follow.
`Settings\NotificationColor` (`app/Models/Settings/NotificationColor.php`), one page at
`pages/settings/master-data/notification-colors/index.tsx` with create/edit/delete in modals.

A notification colour was **reference data with no consumer** for as long as the `notifications`
table it was built for stayed unbuilt. It has one now, and not the expected one:
`tna_template_colors.notification_color_id` ([§6](#6-master-data--tna-templates)) holds a colour so
a TNA milestone can be drawn in it. That first foreign key is what
[§3.5](#35-the-deletion-guard-and-the-debt-it-paid) is now about.

### 3.1 The `notification_colors` table

Built by `2026_08_30_110855_create_notification_colors_table`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `id` | |
| `name` | `string(100)`, **unique, not null** | What the colour means — "Urgent", "Overdue". |
| `color_code` | `char(7)`, **unique, not null** | `#RRGGBB`, stored **uppercase**. |
| `retention_days` | `unsignedSmallInteger`, not null | How many days a thing coloured this way is kept. |
| `status` | `string(1)`, default `'A'` | `App\Enums\RecordStatus` via `App\Concerns\HasStatus`. |
| `inserted_by` | FK → `users.id`, nullable, `nullOnDelete` | `ActorObserver`, as everywhere. |
| `last_updated_by` | FK → `users.id`, nullable, `nullOnDelete` | Same. |

**`retention_days` was requested as `age`.** A colour does not have an age; the value is a duration,
and the column is named for what it measures. Anything that reads as a property of the row but is
actually a measurement gets the same treatment — the name is the only documentation most readers
will see.

**Both `name` and `color_code` are unique.** The name one is obvious. The colour one is the
deliberate choice: a colour here is a *visual signal*, so two rows sharing one makes the signal
ambiguous at a glance, which defeats the table's whole purpose. It is refused at the database, not
merely in the form.

**No index beyond the two unique constraints.** A unique constraint already *is* an index, so both
lookup and `ORDER BY` on either column are covered; the table is bounded by however many colours a
business cares to define; `status` has two values, too low in cardinality to index alone; and InnoDB
indexes the two foreign keys automatically. See
[ARCHITECTURE.md §6.3](../ARCHITECTURE.md#63-migrations).

### 3.2 Uppercasing happens in the request, not in a mutator

`App\Concerns\NotificationColorValidationRules::normalizeColorCode()` runs from
`prepareForValidation()` on both write requests. It trims, uppercases, and adds a leading `#` if one
was omitted, so a pasted `ff0000` is accepted.

**A model mutator would have been a bug**, and this is the trap worth remembering. A mutator runs
*after* validation. `Rule::unique` would therefore compare the raw `#ff0000` against a stored
`#FF0000`, find no match, pass — and hand the collision to the driver as a 500 instead of a field
error. Normalising before validation means the unique rule and the database see the same string.
`tests/Feature/Settings/NotificationColorTest.php` pins this with a test that submits a lowercase
duplicate and expects a `color_code` validation error.

### 3.3 What is filterable, and what deliberately is not

`NotificationColor::FILTERABLE` declares `name` as `Contains`, `color_code` as `Prefix`, and
`status` as `Equals`.

- `color_code` is a **prefix** match because a hex is read and typed left-to-right from the `#`, and
  a prefix stays seekable on the unique index where a leading wildcard would not. The consequence is
  worth knowing: the typed term has to start with `#` to match anything.
- **`retention_days` is not filterable at all.** Every cell in this row is an equality or a string
  match, and an exact-match cell on a duration answers a question nobody asks — "which colours are
  kept for exactly 31 days?". A range filter is a different control with a different wire format; if
  one is ever wanted it is a decision to record, not a key to add to the map. It *is* sortable,
  because ordering by a duration is a question people do ask and it costs nothing.

### 3.4 The colour is stored as a hex, and that is theme-blind

`components/ui/color-input.tsx` pairs a native `<input type="color">` with a hex text field and a
swatch. **Only the text field carries `name`.** The colour input is an unnamed visual control that
writes into it — two named inputs would submit the field twice and the last would win, a bug that
stays invisible until the values disagree. This is the same contract `combobox.tsx` meets with its
hidden input; [ARCHITECTURE.md §8.5](../ARCHITECTURE.md#85-selects-are-comboboxes) now states it for
every compound control.

**The accepted cost: a stored hex does not follow the theme.** A colour chosen while in light mode
is the same hex in dark mode, and nothing re-evaluates it for contrast — a dark navy that reads
perfectly on a white ground can be nearly invisible on a dark one. Two alternatives were considered:

- **A daisyUI semantic token** (`primary`, `warning`, `error`…) chosen from the existing `Combobox`.
  This follows the theme for free, can never produce an unreadable colour, and needs no new
  component — but it is a fixed palette of about eight choices, which is not what "colour picker"
  means.
- **A contrast guard** that computes readability against the theme background and warns on a poor
  choice. This is the only option that *solves* the problem rather than documenting it, and it
  remains the obvious upgrade if unreadable colours turn out to be a real complaint.

Arbitrary colour won because the field is meant to be chosen freely. The swatch preview in the form
and in the table is what lets someone see the choice before and after saving; the form also says so
in as many words. Note this is unrelated to toast colour, which is **not** free-form and comes from
daisyUI tokens for exactly the theme-following reason — see
[ARCHITECTURE.md §8.8](../ARCHITECTURE.md#88-toasts-carry-severity-and-they-clear-themselves).

### 3.5 The deletion guard, and the debt it paid

This section used to read "there is no deletion guard, and that is owed". It was a deliberate
omission rather than a gap: nothing referenced a notification colour, so a `deletionBlocker()` could
only ever return `null` — dead code no test could exercise, reading as a guard while guarding
nothing. The debt was recorded against whichever feature took the first foreign key.

**TNA templates took it.** `NotificationColorService::deletionBlocker()` now counts the templates
whose colour ladder uses the row, and `NotificationColorController::destroy()` flashes a **`warning`**
toast and `return back()`s when that count is non-zero. `warning` rather than `error`, because the
actor can clear the blocker themselves by pointing those templates at another colour — the severity
vocabulary is
[ARCHITECTURE.md §8.8](../ARCHITECTURE.md#88-toasts-carry-severity-and-they-clear-themselves).

The foreign key is `restrictOnDelete` as well, so the database refuses independently. **Both are
wanted, and they are not redundant**: the constraint stops a delete arriving by any other route,
and the blocker is the only one of the two that produces a sentence rather than an
integrity-constraint stack trace.

The guard is in the **service**, not a policy: `Gate::before` grants a super admin every ability, so
a policy denial is bypassed for exactly the account most able to do damage. A refusal about the
*record's* state rather than the actor's power always lives in the service — see
[ARCHITECTURE.md §9.1](../ARCHITECTURE.md#91-rbac-roles--permissions).

**The delete button is not disabled per row**, unlike the designations page. Doing so would mean
shipping a holder count on every row of the list and would go stale the moment somebody else edited
a template; the server decides, and the toast explains. If the register grows large enough that a
refused click is a nuisance, the `is_deletable` flag is the change to make.

Retiring remains the softer verb: setting `status` to `I` removes a colour from the pickers while
leaving every template that uses it working. `status` is not `deleted_at`, and the two are not
interchangeable ([ARCHITECTURE.md §9.3.1](../ARCHITECTURE.md#931-activeinactive-status)).

### 3.6 Permissions: one bucket for all master data

Every master-data table is gated by `settings.master-data.{view,create,update,delete}` — **not** a
permission per table. All four were already in `RolePermissionSeeder`'s catalogue and already
granted (`admin` holds the `settings.` prefix, `merchandiser` holds `settings.master-data.view`), so
this surface needed **no seeder change and no role change at all**.

The trade is real and was accepted: whoever may edit colours may edit sizes, UOM and every future
reference table. Per-table permissions were rejected for a specific reason beyond granularity —
`merchandiser` is granted the *literal string* `settings.master-data.view`, and the seeder's prefix
matcher would not have matched a `settings.notification-colors.view`, so that role would have
silently lost access with nothing failing. A nested
`settings.master-data.notification-colors.view` would have kept prefix matching working but breaks
the three-part `{module}.{resource}.{action}` format that `RoleStoreRequest` and
`PermissionStoreRequest` actually validate.

If a table ever genuinely needs its own permissions, it takes a seeder change **and** a `ROLES` map
change in the same commit.

### 3.7 The screen

One page with modals — the `admin/designations` shape, not the `admin/roles` index/create/edit
shape. It is a small reference table, and three navigations to add one row is the wrong trade. The
choice between the two shapes is a decision each surface makes deliberately; see
[ARCHITECTURE.md §5](../ARCHITECTURE.md#5-module-registry), which warns against harmonising one into
the other without one.

It uses the shared list apparatus unchanged — sortable headers, a filter cell per column, a page
size selector, numbered pagination, all carried in the query string
([ARCHITECTURE.md §8.6](../ARCHITECTURE.md#86-every-list-is-paginated-sortable-and-filtered-per-column)).
Being the **second module** to import that apparatus is what triggered its promotion from
`components/admin/` to `components/shared/`
([ARCHITECTURE.md §6.5](../ARCHITECTURE.md#65-components)).

The page renders under plain `AppLayout`, not the account shell — [§2.1](#21-settingslayout-is-the-account-layout-not-the-settings-layout).

### 3.8 There is no seeder

`database/seeders/Settings/` is empty on purpose. Nothing consumes the table yet, and seeding
invented colours would put rows in a reference table that exist only to make it look populated —
someone would then have to work out which of them were real. The factory covers the tests. Seed it
when the business names its actual colours, or when `notifications` needs something to point at.

---

## 4. Testing

| File | Covers |
| --- | --- |
| `tests/Feature/ListBehaviourTest.php` | The paginate/sort/filter contract, once, across every list surface including this one |
| `tests/Feature/Settings/NotificationColorTest.php` | Only what is specific here: the four permissions, both unique constraints, the hex format and its normalisation, retention bounds, retirement, actor stamping, and that the picker is unpaginated |

The split is the point: the shared contract is asserted once rather than once per surface with drift
between the copies. **A new list surface is added to `surfaces()` in the first file** and inherits
the whole set — that is the cheapest correct thing to do and there is no reason to hand-copy it.

`ListBehaviourTest` moved from `tests/Feature/Admin/` to the root of `tests/Feature/` in this
change, because a file testing module-agnostic apparatus stopped being an Admin file the moment a
Settings surface joined its dataset — the same reasoning that puts `ListRequest` at the root of
`app/Http/Requests/`.

**One gap that the PHP suite cannot close.** `color-input.tsx` is a form control, and
[ARCHITECTURE.md §13.1](../ARCHITECTURE.md#131-never-run-the-suite-with-a-cached-config--and-it-can-no-longer-happen)
records that there is no DOM-level test harness: feature tests post arrays straight to the
controller, so a control that emits the wrong field name ships green. A `<Combobox multiple>` once
shipped emitting `buyers[][]` exactly this way. **A change to `color-input.tsx` is not proven until
someone has submitted the form in a browser.**

---

## 5. Adding the next master-data table

Colors, sizes, UOM, seasons, fabric and trim types, machine types, process stages. Each one is the
same path, and none of them should need a new decision:

1. Migration — flat, `status` char, the two actor columns, and only the indexes a query needs.
2. `php artisan make:model Settings/{Name} -f`, with `HasStatus`, `Listable`, `#[ObservedBy]`,
   `#[Fillable]`, `FILTERABLE` and `SORTABLE`.
3. A `{Name}ValidationRules` trait in `app/Concerns/`, plus `{Name}{Index,Store,Update}Request` —
   the index one extending `ListRequest`.
4. A service in `app/Services/Settings/`, holding any refusal that is about a record's state.
5. A controller mirroring `NotificationColorController`.
6. A route group in `routes/settings.php` under the existing `settings.master-data.` prefix,
   gated by the same four `settings.master-data.*` permissions. **No seeder or role change.**
7. `php artisan wayfinder:generate --with-form` — the flag is not optional, see
   [ARCHITECTURE.md §8.2](../ARCHITECTURE.md#82-wayfinder--generated-never-edited).
8. Page under `pages/settings/master-data/{name}/index.tsx`, types in `resources/js/types/settings.ts`
   re-exported from `types/index.ts`, a sidebar entry in the Settings group.
9. A surface in `ListBehaviourTest`'s `surfaces()` dataset, and a test file for what is specific.
10. A section here.

Steps 6 and 9 are the two most often skipped, and both are silent failures: the first grants nobody
access to a screen that exists, the second leaves a list untested while looking covered.

---

## 6. Master data — TNA templates

The second master-data surface, and the one that proves §5's claim: it cost a route group and
nothing else. No permission, no seeder entry, no role change.

`Settings\TnaTemplate` with two children, `TnaTemplateMilestone` and `TnaTemplateColor`, on one page
at `pages/settings/master-data/tna-templates/index.tsx`. Merchandising's TNA board reads them
through `Merchandising\TnaCalculator` and never writes them.

A template answers two questions about one lead-time band: **when** each milestone falls, and **how
urgently** it reads as the date approaches.

### 6.1 The band is the key, and it is measured

A purchase order's lead time is its ship date minus its BQS date. The obvious register keys on that
number. It was rejected on evidence: the three orders in the reference data run **263, 264 and 265
days** against one BQS, because their ship dates are staggered by a day each. Real lead times are
arbitrary integers, so an exact key needs a row per value and matches nothing the day a fourth order
arrives on day 266.

`lead_time_from` and `lead_time_to` are therefore a band, inclusive at both ends, and one `241–300`
row serves all three. `TnaTest::a single band serves three different lead times` pins it — re-keying
the register on an exact value fails there rather than in production.

### 6.2 Three rules the database cannot carry

All three are enforced in `TnaTemplateValidationRules`, and each is there because schema cannot
express it:

| Rule | Why not the database |
| --- | --- |
| No two **active** bands may overlap | MySQL has no exclusion constraint; SQLite has neither |
| At most one **open-ended** colour band per template | Repeated `NULL`s are permitted in a unique index on both drivers — an index would read as a guard while allowing exactly what it forbids. The same trap is documented on `bqs_sheets.root_id` |
| `Shipment` may not be given an offset | It is read from the purchase order and is the date lead time is measured *to*; a template scheduling it could contradict the order it describes |

The overlap check is **active-only**, and that is the subtlety worth keeping: deactivating a band is
how it is retired without losing the record of it, so a retired band must be free to overlap its
replacement. It cannot match an order either — `TnaTemplate::scopeCovering()` agrees — so the
overlap creates no ambiguity.

### 6.3 The colour ladder, and why it is a table

`notification_colors` holds a name, a hex and a retention period, and **nothing that says which
colour means "late"**. The four rows defined today make that concrete: their `retention_days` are
`Urgent 5`, `Enough 15`, `Good 30`, `Super Urgent 30` — not ordered by urgency and tied at the top.
No existing column can be read as a severity, so this feature declares the ladder itself.

`max_days_remaining` is the inclusive upper bound in days until the planned date. Bands are read
ascending with `null` last and the first that covers wins:

```text
 -1   ->  Super Urgent   the date has passed
  7   ->  Urgent
 21   ->  Enough
null  ->  Good           the catch-all
```

The column is **signed** so a negative bound can mean overdue, and nullable so one rung can mean
"everything further out". The ordering lives on `TnaTemplate::colors()` rather than at each call
site, because a caller that ordered it differently would silently pick the wrong colour rather than
fail.

**It is a child table rather than a JSON column** for one reason: a JSON column cannot carry
`restrictOnDelete`, and protecting a referenced colour is the whole point — see
[§3.5](#35-the-deletion-guard-and-the-debt-it-paid).

Milestones are a child table for a different reason. The proof of concept needs two offsets, which
two columns would have carried; `Master Order recap.xls`, the sheet this models, tracks roughly
**twenty-five** milestone groups. A column each means a migration every time the business names one
and a table seventy-five columns wide. A row each means a new `TnaMilestone` case. The form still
renders exactly two number inputs, driven by the enum, so the UI cost is identical.

### 6.4 Children are replaced, not merged

`TnaTemplateService::update()` deletes both child sets and rewrites them inside one transaction.
Merging would need a stable identity for a rung that the form does not have and the user does not
think in — they edit a ladder, not four rows. The page's repeater keys its rows by a client-side
counter rather than by array index, because the inputs are uncontrolled and index keys make React
reuse a removed row's DOM node for the row that slides up into its place.

### 6.5 There is no deletion guard here, and none is owed

Nothing holds a foreign key to a template. The TNA board matches one at read time and stores no
link, so deleting one changes which schedule an order draws with and destroys no record — a visible
effect, not a corruption. Contrast §3.5, where the guard exists precisely because this feature's
colour rungs *do* hold a key.

Deleting a template does cascade to its children, which is what `cascadeOnDelete` is for: a rung
belongs to the template above it and means nothing without it.

### 6.6 Testing

`tests/Feature/Settings/TnaTemplateTest.php` covers only what is specific: the three cross-field
rules, boundary-touching bands, replacement-on-edit, the cascade, and the notification-colour
deletion guard from both sides. The paginate/sort/filter contract comes from `ListBehaviourTest`'s
`surfaces()` dataset, which this surface joined.

**The same gap applies as in §4.** `tna-template-form-dialog.tsx` posts `milestones[n][...]` and
`colors[n][...]` array field names, and the PHP suite posts arrays straight to the controller — so a
repeater emitting the wrong shape would ship green. **The dialog is not proven until someone has
saved a template in a browser.**
