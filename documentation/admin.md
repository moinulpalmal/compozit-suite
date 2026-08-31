# Admin Module — Reference

> **Scope.** This file explains *what the Admin surfaces do and why they are built this way*.
> Where things live and what they are called is [`ARCHITECTURE.md`](../ARCHITECTURE.md)'s job — this
> file links to it rather than restating it, so a decision never has two copies that can disagree.
>
> Update this file in the same change as any Admin surface it describes.

---

## 1. Overview

| Sub-area | Backend | Pages | Status |
| --- | --- | --- | --- |
| a. User management | `Admin\UserController` | `pages/admin/users/index.tsx` | ✅ built |
| b. RBAC — roles & permissions | `Admin\RoleController`, `Admin\PermissionController` | `pages/admin/{roles,permissions}/` | ✅ built |
| c. Buyer-wise user access control | `Admin\UserController::updateBuyerAccess`, `Admin\BuyerAccessService` | a dialog on `pages/admin/users/index.tsx` | ✅ built |
| d. Buyer setup & management | `Admin\BuyerController` | `pages/admin/buyers/index.tsx` | ✅ built |
| e. Audit logging | `Admin\AuditLogController` | `pages/admin/audit-logs/` | 🟡 scaffolded |
| f. Designations | `Admin\DesignationController` | `pages/admin/designations/index.tsx` | ✅ built |

Admin is the only module allowed to write roles, permissions, buyer-access assignments, designations
and audit records. `User` lives at `app/Models/User.php`, not `app/Models/Admin/` — authentication is
a whole-app concern that Admin happens to administer.

Designations are **HR reference data**, and HR reference data is Admin-owned even though product
reference data (colors, sizes, UOM…) belongs to Settings. That split, and why it overrode the
original "all master data is Settings-owned" rule, is recorded in
[ARCHITECTURE.md §9.4](../ARCHITECTURE.md#94-master-data).

---

## 2. User management — backend

### 2.1 The `users` table

Built by `0001_01_01_000000_create_users_table`, then
`2026_08_25_113553_add_theme_to_users_table`,
`2026_08_27_082636_add_employee_fields_to_users_table` and
`2026_08_27_091435_add_indexes_to_users_table`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigIncrements` | |
| `name` | `string` | The display name. Searched with a leading-wildcard `like`. |
| `employee_id` | `string(10)`, **unique, not null** | **The login identifier.** `/^[A-Za-z0-9-]{3,10}$/`, stored verbatim. See [§4](#4-authentication). |
| `personal_mobile_no` | `string(11)`, nullable | `/^01[3-9][0-9]{8}$/` — Bangladeshi format. |
| `official_mobile_no` | `string(11)`, nullable | Same rule. |
| `official_extension_no` | `string(4)`, nullable | Up to four digits. |
| `gender` | `string(1)`, default `'M'` | Cast to `App\Enums\Admin\Gender` — `M` / `F` / `O`. |
| `designation_id` | FK → `designations.id`, **nullable**, `nullOnDelete` | Required by the form requests, not by the column. See [§8](#8-designations). |
| `status` | `string(1)`, default `'A'` | Cast to `App\Enums\RecordStatus` via `HasStatus`. Was a boolean `approved` — see [§9.1](#91-usersapproved-became-usersstatus). |
| `approval_authority` | `boolean`, default `false` | **Parked.** See [§2.2](#22-approval_authority-is-not-wired-to-anything). |
| `inserted_by` | FK → `users.id`, nullable, `nullOnDelete` | Written only by `ActorObserver`. |
| `last_updated_by` | FK → `users.id`, nullable, `nullOnDelete` | Written only by `ActorObserver`. |
| `email` | `string`, unique | Still required — the password-reset broker is keyed on it. |
| `email_verified_at` | `timestamp`, nullable | Set to `now()` for admin-created accounts. |
| `password` | `string` | `hashed` cast; never assign a pre-hashed value. |
| `theme` | `string`, nullable | Cast to `App\Enums\Theme`; owned by appearance settings. |
| `deleted_at` | `timestamp`, nullable | `SoftDeletes`. |

**Three columns departed from the original request, deliberately:**

1. `employee_id` was specified as `->default('6655')->unique()`. A default on a unique column works
   exactly once — the second row that omits the value collides. It is required instead, with no
   default, validated and live-checked.
2. `inserted_by` / `last_updated_by` were specified as `string` columns defaulting to
   `'Startup Admin'`. They are nullable foreign keys instead, so a rename does not orphan the trail.
   The consequence is that the `'Startup Admin'` default is gone: **writes with no authenticated
   actor — seeders, migrations, console commands — leave both columns `NULL`.**
3. `approved` / `approval_authority` were specified as `->default(x)->nullable()`. A default *and*
   nullable encodes two different "unknown" states. Both are non-nullable with defaults.

### 2.1.1 Indexes — every one of them measured

`users` carries twelve indexes. **Each was justified by a measurement, not a guess**, and several
plausible-looking candidates were rejected because the numbers said no. The general rule is
[ARCHITECTURE.md §6.3 Indexing](../ARCHITECTURE.md#63-migrations); the tool is
[§2.1.3](#213-the-benchmark).

| Index | Serves | Source |
| --- | --- | --- |
| `PRIMARY` | `id` | — |
| `users_email_unique` | login-adjacent lookups, availability check **and** email sort. Not the email *filter* — that is `Contains` now | unique constraint |
| `users_employee_id_unique` | login, availability check, employee-ID prefix filter **and** ID sort | unique constraint |
| `users_inserted_by_foreign` | FK integrity | InnoDB, automatic |
| `users_last_updated_by_foreign` | FK integrity | InnoDB, automatic |
| `users_deleted_at_name_index` | default view, name sort, both counts — and it still serves the `ORDER BY` under a `%name%` filter | `…091435_add_indexes_to_users_table` |
| `users_deleted_at_created_at_index` | "Added" sort | `…094614_add_data_table_indexes_to_users_table` |
| `users_deleted_at_personal_mobile_index` | mobile prefix filter | same |
| `users_deleted_at_official_mobile_index` | mobile prefix filter | same |
| `users_deleted_at_extension_index` | extension prefix filter | same |
| `users_deleted_at_status_name_index` | status filter + default sort | `…131928_change_users_approved_to_status` (replaced the `approved` original) |
| `users_designation_id_foreign` | FK integrity **and** the designation filter | InnoDB, automatic |

Every composite leads with `deleted_at` because every query on this table has it — the soft-delete
scope is always applied.

**Measured at 5,000 rows on MySQL 9.6** (`php artisan users:benchmark`, median of five runs):

| Query | Before | After | Index |
| --- | --- | --- | --- |
| Sort by "Added" (`created_at desc`) | **11.34 ms** + filesort | **0.33 ms** | `…created_at_index` |
| Extension lookup (selective prefix) | 6.65 ms | **0.19 ms** | `…extension_index` |
| Mobile lookup (selective prefix) | 6.65 ms | **0.28 ms** | `…personal_mobile_index` |
| Inactive-user filter | 1.25 ms | **0.25 ms** | `…approved_name_index`, since rebuilt as `…status_name_index` |

**Rejected candidates — do not re-add without new measurements:**

| Candidate | Why not |
| --- | --- |
| `(deleted_at, employee_id)` | Sorting by employee ID was **slower** with it: 0.42 ms → 0.95 ms. MySQL already walks `users_employee_id_unique` in order and stops at the page limit. |
| `(deleted_at, email)` | Same story via `users_email_unique`; 0.56 ms → 0.47 ms is inside the noise. |
| `(deleted_at, gender, name)` | Gender has three values — not selective. Both plans sub-millisecond (0.35 ms → 0.29 ms). |
| `(deleted_at, designation_id, name)` | **The planner never chose it.** With the composite installed, the rare-designation filter still planned on `users_designation_id_foreign + filesort` at 1.58 ms — indistinguishable from the 1.29–1.66 ms it runs at without. The FK index `constrained()` already creates is enough, exactly as [§6.3](../ARCHITECTURE.md#63-migrations) predicts. |

**The status index was re-measured when `approved` became `status`**, rather than assumed to carry
over. `(deleted_at, status, name)` is chosen by the planner on every run at 0.28–0.47 ms, against the
boolean version's 0.25 ms — the same plan, within noise. It is a shipped index now, so it is no
longer a benchmark *candidate*; a candidate duplicating a shipped index measures nothing.

`designation_id` was expected to be the case where a composite finally earned its place — it has far
more distinct values than `gender`, which was rejected for low cardinality. It did not. The reason is
the FK index: unlike `gender`, `designation_id` already *has* an index, so the only thing a composite
could add is the `ORDER BY name`, and MySQL preferred filtering through the FK index and sorting 25
rows over walking a wider composite. The **common** designation — a third of the table — is scanned
via `users_deleted_at_name_index` at ~1 ms either way, which is the right plan for a filter that
selective. Two different reasons, same verdict: measure, don't reason by analogy.

Two lessons worth keeping:

- **A unique index already serves sorting**, not just lookups, when the query is paginated. That is
  why the two "obvious" sort composites were rejected.
- **Selectivity decides whether an index is used at all.** A mobile search for `017%` matches a
  seventh of the table and MySQL correctly ignores every index for it; a search for `01712345%`
  matches one person and the index wins by 24×. An early benchmark that only tested the broad case
  nearly rejected three good indexes.

`users_deleted_at_official_mobile_index` is the one index accepted **by symmetry** rather than
measurement — it is the identical query shape on an identical column, and the factory previously
left `official_mobile_no` null so the benchmark had no data for it. The factory now populates it, so
the next benchmark run covers it directly.

### 2.1.2 Matching is declared per column

> This section previously argued that **all** search is prefix-matched, on one field chosen from a
> selector. Half of that is now reversed. The single search box became a filter cell under every
> column, and text columns split into two behaviours instead of sharing one. The reasoning below
> replaces the old rule; it does not sit alongside it.

`User::FILTERABLE` names a `FilterType` for each column, and `Listable::scopeFilterColumns()`
dispatches on it:

| Column | Type | Why |
| --- | --- | --- |
| `name`, `email` | `Contains` — `LIKE '%term%'` | Finding a name mid-string is what people expect of a name box, and worth a scan |
| `employee_id` | `Prefix` — `LIKE 'term%'` | The login identifier, covered by a unique index, and how anyone types an ID |
| `personal_mobile_no`, `official_mobile_no`, `official_extension_no` | `Prefix` | Each has a measured index built for exactly this seek — see §2.1.1 |
| `gender`, `designation_id`, `status` | `Equals` | Dropdown cells; an equality, not a text match |

**The choice is declared, never inferred from the column type.** Every filterable column here is a
`varchar` — `employee_id` holds `U3` for backfilled users, and the phone numbers are strings because
leading zeros matter — so type inference would make the entire table `Contains` and quietly retire
three indexes.

The visible consequence: **"868" finds employee 15868 only where the column is `Contains`, and
`employee_id` is not.** Each cell's placeholder says which it is ("Contains…" versus "Starts with…")
rather than leaving people to discover it.

**Cells are `AND`-ed, which is what keeps this indexable.** It is `OR`-ing one term across six
columns that MySQL handles worst — an unreliable index merge, or one index chosen and the rest
filtered. With a cell per column the leading predicate seeks and the others are residual filters.

Every term is escaped with `addcslashes($term, '%_\\')` so a user typing `%` gets a literal match
rather than a full-table wildcard scan. There is a test pinning that, for both match types.

**What `%term%` actually cost**, measured on 5,000 rows after the change (`php artisan
users:benchmark`):

| Query | ms | Index chosen |
| --- | --- | --- |
| unfiltered page | 0.33 | `users_deleted_at_name_index` |
| `name` contains, broad | 0.51 | `users_deleted_at_name_index` |
| `name` contains, selective | **1.49** | `users_deleted_at_name_index` |
| `employee_id` prefix | 0.31 | `users_employee_id_unique` + filesort |
| `personal_mobile_no` prefix | 0.29 | `users_deleted_at_personal_mobile_index` + filesort |
| `official_extension_no` prefix | 0.29 | `users_deleted_at_extension_index` + filesort |
| two cells (`name` contains + `status`) | 0.39 | `users_deleted_at_name_index` |

**No index was dropped, and the measurement is why.** The three prefix indexes are still chosen, so
they still earn their write cost. `users_deleted_at_name_index` survives the contains filter too —
not as a range seek, which a leading wildcard makes impossible, but as an ordered walk that supplies
`ORDER BY name` and stops at the `LIMIT`, with the wildcard applied as a residual filter. The price
of contains is the 1.49 ms row: roughly 4× an unfiltered page, and the slowest thing on this list.

`FULLTEXT` remains **deferred**: it is word-boundary based (a mid-word fragment still would not
match — the very case `%term%` was chosen for), `innodb_ft_min_token_size` is 3 on this server so
1–2 character terms would return nothing, and Laravel's SQLite grammar has no `compileFullText`, so
the migration would throw under test and the production `MATCH` path would ship uncovered by CI.
Revisit if the contains scan becomes a problem at real table sizes.

`FULLTEXT` was considered for true "contains" search and **deferred**: it is word-boundary based (a
mid-word fragment still would not match), `innodb_ft_min_token_size` is 3 on this server so 1–2
character terms would return nothing, and Laravel's SQLite grammar has no `compileFullText` — so a
migration would throw under test and the production `MATCH` path would ship uncovered by CI.
Revisit only if prefix matching proves insufficient in practice.

### 2.1.3 The benchmark

```bash
php artisan users:benchmark --rows=5000
```

`app/Console/Commands/BenchmarkUsersCommand.php` seeds throwaway users, times every query pattern
the list issues with and without each candidate index, prints which index MySQL actually chose, then
deletes the seeded rows and drops any index it created. It **refuses to run outside `local`**.

Use it before adding any index to this table, and re-run it as the table grows — the right answer at
5,000 rows is not necessarily the right answer at 50,000. Note that deep pagination
(`LIMIT 25 OFFSET 2000`) is ~3 ms regardless of indexing; that is offset cost, which no index fixes.

Development runs on MySQL and tests on in-memory SQLite — see
[ARCHITECTURE.md §2](../ARCHITECTURE.md#2-stack). That split is why the FK columns are indexed in
development but not under test.

### 2.2 `approval_authority` is not wired to anything

The column exists and is editable, but **no code reads it**. There is no pending-approval queue, no
maker/checker flow, and `approved` defaults to `true`, so a newly created user can sign in
immediately. `approved = false` is an "account disabled" switch, not a "waiting for approval" state.

If an approval workflow is built later, decide what `approval_authority` means *before* writing code
against it, and record the decision here.

### 2.3 Permissions

Eight, all in the enforced `{module}.{resource}.{action}` shape and seeded by
`Database\Seeders\Admin\RolePermissionSeeder`:

| Permission | Gates |
| --- | --- |
| `admin.users.view` | The list page, both tabs, and the search |
| `admin.users.create` | The New user modal and `POST admin/users` |
| `admin.users.update` | The Edit modal — profile and HR fields only |
| `admin.users.delete` | Soft delete (move to Historical) |
| `admin.users.restore` | Restore from Historical |
| `admin.users.force-delete` | Permanent deletion |
| `admin.users.reset-password` | Setting another user's password |
| `admin.users.assign-roles` | Changing another user's roles |

`assign-roles` is separate from `update` on purpose: editing someone's phone number should not imply
the power to widen their access. Route-level gating is `permission:` middleware in
`routes/admin.php`; `Admin\UserPolicy` mirrors it for `Gate::authorize()` and `can` props.

### 2.4 Call path

`routes/admin.php` → `Admin\UserController` → `Admin\UserService` → `User`.

The controller is orchestration only: validate via a form request, ask the service whether a guard
blocks the action, delegate, flash a toast, `back()`. Every write returns `back()` rather than
redirecting to the index, because every action happens in a modal on the index already.

`UserController::describe()` shapes each row for the table, including two computed flags the UI
needs — `is_self` and `is_last_super_admin` — so the front end can disable actions the server would
refuse anyway.

### 2.5 The four escalation guards

Without them, `admin.users.assign-roles` is a privilege escalation: its holder could grant themselves
`super-admin`, whose `Gate::before` bypass passes every check in the application.

| Guard | Where | Behaviour |
| --- | --- | --- |
| Only a super admin may **grant** `super-admin` | `Concerns\RoleAssignmentRules::assignableRoleRule()` | Validation error on `roles.*` |
| Only a super admin may **revoke** `super-admin` | `UserService::roleAssignmentBlocker()` | Error toast, no change |
| Nobody changes **their own** roles | `UserService::roleAssignmentBlocker()` | Error toast, no change |
| Nobody deletes **their own** account from Admin | `UserService::deletionBlocker()` | Error toast; self-deletion stays in `settings/profile` |
| The **last** super admin cannot be deleted or deactivated | `UserService::deletionBlocker()` / `approvalBlocker()` | Error toast |

**These do not live in `UserPolicy`, and must not be moved there.** `Gate::before` grants a super
admin every ability, so a policy denial is bypassed for precisely the account the guard exists to
constrain. The reasoning is recorded in
[ARCHITECTURE.md §9.1](../ARCHITECTURE.md#91-rbac-roles--permissions) and the same pattern already
governs the super-admin role in `Admin\RoleController`.

`UserService::assignableRoleNames()` filters `super-admin` out of the picker for everyone else, so
the UI never offers what the server would refuse.

### 2.6 Actor stamping

`app/Observers/ActorObserver.php` sets `inserted_by` on create and `last_updated_by` on update from
`Auth::id()`. Neither column is in the model's `#[Fillable]` list, so the observer is the only
writer and *every* path — Admin screens, account settings, console — is stamped identically. This is
narrower than the audit-log mechanism, which is still undecided
([ARCHITECTURE.md §9.3](../ARCHITECTURE.md#93-audit-logging)).

It is **one shared observer**, typed against `Model` and attached with `#[ObservedBy]` to every model
carrying the two columns — `User` and `Admin\Designation` today. It was `UserObserver` until
designations became the second stamped table; a hand-copied second observer is precisely what would
break the "stamped identically" guarantee the first paragraph makes.

### 2.7 Validation

| Trait | Supplies |
| --- | --- |
| `Concerns\EmployeeValidationRules` | `employee_id`, mobiles, extension, gender, the booleans, and the error messages |
| `Concerns\ProfileValidationRules` | `name`, `email` — shared with account settings |
| `Concerns\PasswordValidationRules` | `Password::default()` + `confirmed` |
| `Concerns\RoleAssignmentRules` | The `roles[]` list and the super-admin grant guard |

`RoleAssignmentRules` was **extracted from** `RbacValidationRules` in this change so the user
requests can reuse the role-list rules without inheriting the abstract `nameFormatMessage()` that
only the role and permission forms need. `RbacValidationRules` now `use`s it, so the permission
requests are unchanged.

`Rule::unique` queries the table directly and therefore **sees soft-deleted rows**. That is what
makes reusing a deleted user's employee ID or email fail, with a message pointing the admin at the
Historical tab.

---

## 3. User management — frontend

### 3.1 One page, two tabs

`resources/js/pages/admin/users/index.tsx` is the whole surface. Active and Historical are tabs over
a `?filter=active|trashed` query parameter, not two routes, and every mutation happens in a modal.

**The table is filtered, sorted and paginated**, all through the query string so any view is a
shareable URL:

| Parameter | Values |
| --- | --- |
| `view` | `active` (default) / `trashed` |
| `sort` | `name` (default), `employee_id`, `email`, `created_at` — `User::SORTABLE` |
| `direction` | `asc` (default) / `desc` |
| `filter[…]` | one key per `User::FILTERABLE` column, max 100 chars each |
| `per_page` | `10` (default) / `25` / `50` / `100` |
| `page` | paginator page |

The tab is **`view`, not `filter`**. It was `filter=active|trashed` until the filter row arrived and
claimed `filter[...]`; a scalar and an array cannot share one query-string key. `view` is the better
name anyway — it picks the record set, which is exactly why it is not a cell in the filter row.

`UserIndexRequest` validates all of it and `UserIndexRequest::filters()` applies the defaults, so the
controller never sees a half-specified state. Changing any filter resets to page 1 — staying on
page 9 of a result set that now has two pages would show an empty table.

**Sort columns and filter keys are allow-listed twice**, in the request and again in the model scope.
That is not redundancy for its own sake: passing request input to `orderBy()` is a SQL injection,
and the scope is the layer that guarantees it cannot happen even if the query is ever built from
somewhere other than this controller. `UserTest` pins it with `?sort=name; DROP TABLE users`. An
unknown `filter[…]` key is likewise an error rather than a silent ignore — a typo'd filter must not
look like a filter that found nothing.
This deliberately diverges from the `index/create/edit` pattern that roles and permissions use —
see [ARCHITECTURE.md → Module 1](../ARCHITECTURE.md#5-module-registry). Do not harmonise one into
the other without a new decision.

### 3.1.1 The list apparatus, and what is no longer here

The toolbar, sortable headers, column filters and pagination that this page pioneered are now shared
by all four Admin lists —
[ARCHITECTURE.md §8.6](../ARCHITECTURE.md#86-every-list-is-paginated-sortable-and-filtered-per-column).

Things that moved out of this page, so look for them in their new homes:

| Was | Now |
| --- | --- |
| `components/admin/users-table-toolbar.tsx` | `components/admin/list-toolbar.tsx` — and most of what it held is now `components/admin/column-filter-row.tsx` |
| A `SortableHeader` local to `pages/admin/users/index.tsx` | `components/admin/sortable-header.tsx`, plus `nextSort()` |
| `User::scopeSearch()` / `scopeSortBy()` | `App\Concerns\Listable::scopeFilterColumns()` / `scopeSortBy()`. `User::FILTERABLE` and `SORTABLE` stayed on the model |
| The page's own debounce-free `router.get` helper | `hooks/use-list-filters.ts` |
| `UserController::PER_PAGE` (copied into all four controllers) | `ListRequest::PER_PAGE_OPTIONS` / `DEFAULT_PER_PAGE`, and a user-selectable `per_page` |
| `UserIndexRequest extends FormRequest` | `extends App\Http\Requests\ListRequest`; only the `view` tab and three dropdown-cell rules are still declared here |

All of these stay in `components/admin/`, **not** `components/shared/`: §6.5's promotion rule fires
when a second *module* imports a component, not a second surface, and all four lists are Admin.

The users list's behaviour did not change in that refactor. Its existing suite was the regression
test, and it stayed green throughout.

### 3.2 Components

| Component | Purpose |
| --- | --- |
| `admin/user-form-dialog.tsx` | Create and edit. Password and role fields render only when creating. Also exports `PasswordStrength`. |
| `admin/user-role-dialog.tsx` | Replaces a user's role set wholesale. |
| `admin/user-password-dialog.tsx` | Sets another user's password — no current-password prompt, because the actor is not the owner. |
| `admin/confirm-action-dialog.tsx` | Generic confirm-then-submit over a Wayfinder `.form()`. Takes the trigger as `children`. |
| `admin/confirm-delete-dialog.tsx` | The destructive icon-button preset over the above. Roles and permissions still use it unchanged. |
| `admin/column-filter-row.tsx` | The `<tr>` of filter cells under the headers — text, dropdown, stacked, or empty. Shared by all four lists. |
| `admin/list-toolbar.tsx` | The thin bar above the table: rows-per-page, Clear filters, and surface extras such as this page's Active/Historical tabs. |
| `admin/sortable-header.tsx` | Clickable `<th>` and `nextSort()`. Shared by all four lists. |
| `admin/pagination.tsx` | Numbered pages with previous/next. Moves to `components/shared/` the moment a second module imports it, per [ARCHITECTURE.md §6.5](../ARCHITECTURE.md#65-components). |

A cell can be `stack`ed because a table column is not always one database column: this page shows
the email under the name and two numbers under "Contact", so those headings carry two and three
boxes respectively. Stacked boxes name themselves in their placeholder instead of showing the shared
match hint, which is the one place the "Contains…" / "Starts with…" signal is not on screen.

Every dropdown on these screens is `components/ui/combobox.tsx`, not a native `<select>` — there are
none left in the application. It shows its search input only above ten options, so Gender and Status
stay one click while Designation is typeable. See
[ARCHITECTURE.md §8.5](../ARCHITECTURE.md#85-selects-are-comboboxes); the hidden-input detail there
is what keeps these uncontrolled `<Form>`s submitting.

There is no SweetAlert dependency. Confirmations are the project's own `components/ui/dialog.tsx`
(a native `<dialog>` styled with daisyUI) and outcomes are reported through `sonner` via
`Inertia::flash('toast', …)` and the existing `useFlashToast` hook.

### 3.3 `hooks/use-availability.ts`

Debounced live uniqueness check backing the employee-ID and email fields.

- Fires at **3 characters**, after **400 ms** of quiet.
- States: `idle` → `checking` → `available` | `taken`.
- Results are keyed by the value they describe, so a late response for a stale keystroke is simply
  never matched — no cancellation bookkeeping.
- Uses `fetch` + `AbortController`, not `useHttp`, because this is a read with no form state behind
  it. The reasoning is recorded in
  [ARCHITECTURE.md §8.4](../ARCHITECTURE.md#84-inertia-v3-notes).

It is a convenience. `GET admin/users/availability` is gated by
`role_or_permission:admin.users.create|admin.users.update`, and the form requests are what actually
enforce uniqueness.

### 3.4 Validation UX

Server `FormRequest` errors render inline through `InputError`. On top of that, purely for feedback:
the availability indicator, blur-triggered format checks for employee ID and mobile numbers, and a
password strength read-out. The client patterns in `user-form-dialog.tsx` are copies of the server
regexes and are commented as such — **change both together**.

### 3.5 Permission-gated UI

`useCan()` hides actions the signed-in user cannot perform, and `app-sidebar.tsx` hides the Users
link without `admin.users.view`. Row actions are additionally disabled for `is_self` and
`is_last_super_admin`. **None of this is authorization** — the route middleware, the policy and the
service guards are. It only avoids offering an action that would be refused.

---

## 4. Authentication

Users sign in with `employee_id`, not email. The full contract — why `lowercase_usernames` must be
`false`, why matching is case-sensitive, and why password reset stays keyed on `email` — is in
[ARCHITECTURE.md §9.6](../ARCHITECTURE.md#96-authentication-identity).

Two consequences worth having in mind here:

- A soft-deleted user cannot authenticate, because the auth user provider applies the soft-delete
  scope. Their employee ID and email stay reserved until they are permanently deleted.
- Admin-created accounts get `email_verified_at = now()`. There is no self-registration flow, so
  there is nothing to confirm; the `verified` middleware on every module route group would otherwise
  have to be reasoned about per-user.

---

## 5. RBAC surfaces

Roles and permissions keep the module's standard shape: resource routes with `show` excluded, gated
per action by `permission:` middleware, a `{Model}Service` for writes, form requests that enforce
the name formats, and `index`/`create`/`edit` pages. Shared form pieces are `admin/role-form.tsx`,
`admin/permission-form.tsx` and `admin/permission-picker.tsx`.

Permission names are `{module}.{resource}.{action}`, enforced by regex in `RbacValidationRules`.
Roles are data — never check a role name in code, check the permission. `Role::SUPER_ADMIN` is the
single exception, and exists only so the `Gate::before` bypass and the immutability guards have
something to name.

### 5.1 The permission list is flat, and used not to be

`pages/admin/permissions/index.tsx` rendered one `<section>` per module, grouped client-side with a
`useMemo` over the whole catalogue and filtered by a `String.includes()` box.

**Grouping and pagination cannot both hold.** A group gets cut across a page boundary — page 1 ends
midway through `admin.users`, page 2 opens with the remainder under no heading. When every Admin
list became paginated ([ARCHITECTURE.md §8.6](../ARCHITECTURE.md#86-every-list-is-paginated-sortable-and-filtered-per-column)),
the grouping had to give way. It became:

- a **Module column** on each row, and
- a **module filter cell** under it, served by `Permission::scopeModule()` and
  `PermissionService::moduleOptions()`. It is declared `FilterType::Scope` because the module is the
  first dot-delimited segment of `name`, not a column of its own.

`scopeInModule()` matches `"{module}.%"`, appending the dot on purpose: a bare `admin%` prefix would
also sweep in a neighbouring `administration.*` module. There is a test pinning that.

The module is derived from the name rather than stored — it *is* the first dot-delimited segment,
and a second table for a dozen implied values would be one more thing to keep in step.

**The role form's permission picker still groups by module.** It runs
`PermissionService::groupedByModule()`, a different query, and is deliberately untouched — the same
list-versus-picker distinction that governs designations ([§8.3](#83-the-picker-excludes-inactive-designations--with-one-exception)).
The client-side filter box also went: search is now the shared prefix-matched one, so it is
server-side and indexable rather than a `includes()` over every row.

---

## 6. How to extend

### Adding a field to `users`

1. Migration (flat, additive) — see [ARCHITECTURE.md §6.3](../ARCHITECTURE.md#63-migrations).
2. `User`: `#[Fillable]`, cast, `@property` docblock. Leave it out of `#[Fillable]` if only an
   observer should write it.
3. `Concerns\EmployeeValidationRules`: a rule method, wire it into `employeeRules()`, and add a
   message to `employeeMessages()`.
4. `UserController::describe()` so the row carries it.
5. `types/admin.ts` → `UserListItem`.
6. `admin/user-form-dialog.tsx` → a `<Field>` in the grid. If it is a choice, that is a
   `<Combobox>`, not a `<select>` — there are none left.
7. `UserFactory`, then a test in `tests/Feature/Admin/UserTest.php`.
8. Update the table in [§2.1](#21-the-users-table).

### Adding a list screen

1. `use Listable` on the model, and declare `FILTERABLE` / `SORTABLE`. Pick each column's
   `FilterType` deliberately — see [§2.1.2](#212-matching-is-declared-per-column).
2. `{Model}IndexRequest extends ListRequest` — implement `sortable()` and `filterable()`, and add
   `filterRules()` / `filterValues()` only for a dropdown cell's enum rule or a record-set switch.
3. Controller: `->filterColumns($filters['filter'])->sortBy(…)->paginate($filters['per_page'])
   ->withQueryString()->through(…)`, and pass `sortable`, `filterable`, `perPageOptions` and
   `filters` as props.
4. Page: `useListFilters`, `<ListToolbar>`, `<SortableHeader>` per sortable column, a
   `<ColumnFilterRow>` with **one cell per table column** — `{ type: 'none' }` included — and
   `<Pagination>`. Name every prop that varies with the rows in the hook's `only` list.
5. **Add the surface to `surfaces()` in `tests/Feature/Admin/ListBehaviourTest.php`** — it then
   inherits the whole shared contract for free.

Check before you start that the records are not also offered by a picker somewhere. If they are,
that query stays unpaginated; see [§8.3](#83-the-picker-excludes-inactive-designations--with-one-exception).

Step 3 is the one people miss. `designation_id` went through it, which is why the "choose a
designation" message reads like a person wrote it.

### Adding an `admin.users.*` permission

1. Add the action to `RolePermissionSeeder::CATALOGUE` under `'admin.users'`.
2. Re-seed: `php artisan db:seed --class="Database\Seeders\Admin\RolePermissionSeeder"` (idempotent).
3. Add the route with its `permission:` middleware, and a method to `UserPolicy`.
4. Gate the UI with `useCan()`.
5. Update the table in [§2.3](#23-permissions).

If the new action can be aimed at the actor themselves or at the last super admin, add a blocker to
`UserService` — **not** to `UserPolicy`. See [§2.5](#25-the-four-escalation-guards).

---

## 7. Testing

`tests/Feature/Admin/UserTest.php` covers every route's permission gate, the validation rules,
the availability endpoint, the soft-delete/restore/force-delete cycle, password reset, role
assignment, all the escalation guards, and observer stamping. Helpers `userWithPermissions()` and
`superAdmin()` come from `tests/Pest.php`.

```bash
php artisan test --compact --filter=User
php artisan test --compact          # the login change reaches Auth and Settings too
```

Two suites outside this module depend on decisions made here and must be kept in step:
`tests/Feature/Auth/AuthenticationTest.php` (login posts `employee_id`, and the rate-limit key is
built from it) and `tests/Feature/Settings/ProfileUpdateTest.php` (account deletion is now a soft
delete — note that `$model->fresh()` bypasses the soft-delete scope, so assert with
`assertSoftDeleted()` or a scoped query).

`tests/Feature/Admin/DesignationTest.php` covers the designation surface. Note that
`userPayload()` in `UserTest.php` now supplies a `designation_id`, because the form requests require
one — that helper is the single seam every user-creating test goes through.

---

## 8. Designations

A designation is a **job title**. `Admin\Designation` (`app/Models/Admin/Designation.php`), one page
at `pages/admin/designations/index.tsx` with create/edit/delete in modals, gated by
`admin.designations.{view,create,update,delete}`.

**A designation grants nothing.** Permissions come from roles ([§5](#5-rbac-surfaces)) and approval
power from `approval_authority`. Never branch on a designation in an authorization check — if you
find yourself wanting to, the thing you want is a permission.

### 8.1 The `designations` table

Built by `2026_08_27_105912_create_designations_table`.

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `id` | |
| `name` | `string(100)`, **unique, not null** | The title. |
| `short_form` | `string(50)`, nullable, **unique when present** | A unique index permits repeated `NULL`s on MySQL *and* SQLite, so "no code yet" stays legal while duplicate codes are refused. |
| `status` | `string(1)`, default `'A'` | Cast to `App\Enums\Admin\DesignationStatus` — `A` / `I`. |
| `inserted_by` | FK → `users.id`, nullable, `nullOnDelete` | `ActorObserver`, as on `users`. |
| `last_updated_by` | FK → `users.id`, nullable, `nullOnDelete` | Same. |

**No index beyond the two unique constraints.** A unique constraint already *is* an index, the table
holds tens of rows, and `status` has two values — far too low in cardinality to index alone
([ARCHITECTURE.md §6.3](../ARCHITECTURE.md#63-migrations)).

The proposed schema had `name` and `short_form` both nullable with no unique constraint, and
`inserted_by`/`last_updated_by` as bare `bigInteger` columns. All four were tightened: a nameless or
duplicated designation is a data bug the table should refuse, and unconstrained actor columns would
have diverged from `users` and had no observer path.

### 8.2 `status` is a char, and it is not `deleted_at`

`designations.status` is `'A'`/`'I'`, cast through `App\Enums\RecordStatus` and wired up by
`App\Concerns\HasStatus`. **This is now the application's only active/inactive vocabulary** — see
[§9](#9-recordstatus-is-the-one-activeinactive-vocabulary).

> This section previously argued the opposite: that `designations.status` and `users.approved` said
> the same thing two different ways, that the divergence was deliberate, and that neither should be
> harmonised. That held only while designations were the sole char-flagged table. When the owner
> made `RecordStatus` the shared convention, `users.approved` became the outlier rather than the
> precedent, and it was migrated to `users.status`. The rule is reversed, not merely amended.

The consequence is that this screen has **two verbs where a soft-deleting table has one**:

| Verb | Effect |
| --- | --- |
| **Deactivate** (`status = 'I'`, part of `update`) | Removed from the user form's picker. Existing holders keep it. Still listed on this screen and in the users-list filter. |
| **Delete** (`destroy`) | Row is gone. **Refused while anybody holds it**, soft-deleted users included. |

Activating and deactivating is part of `admin.designations.update`, not a permission of its own —
unlike `assign-roles`, toggling a descriptive label grants nobody any power.

`DesignationService::deletionBlocker()` is what refuses the delete, and it counts holders
`withTrashed()`: a user on the Historical tab still carries the title, and deleting it underneath
them would silently blank the field if they were ever restored. The refusal surfaces as an error
toast, not a 403 — it is a fact about the record, not about the actor, which is the same reasoning
that puts the user guards in `UserService` ([§2.5](#25-the-four-escalation-guards)).

### 8.3 The picker excludes inactive designations — with one exception

`DesignationService::assignableOptions(array $keep)` returns active designations **plus any id in
`$keep`**. `UserController::index()` passes the designations its current page of rows already holds.

Without that exception, opening the edit modal for someone holding a since-retired title would show
a select that does not contain their value — blanking the field on save, or failing validation on
something the admin never touched. `EmployeeValidationRules::designationRules($userId)` grants the
same exception server-side, per user. **The two must agree**: if you change one, change the other.

**The designation list is paginated; this picker is not.** They are two queries against one table,
and conflating them would mean a user could not be given any title outside the list's current page.
There is a test pinning it — 40 designations, list paginated at 25, picker still offering all 40.

#### The picker is also the app's one async combobox

It is the single consumer of `<Combobox searchUrl>`
([ARCHITECTURE.md §8.5](../ARCHITECTURE.md#85-selects-are-comboboxes)), because it is the first list
that outgrew being shipped whole. `admin.designations.options` serves it —
`DesignationService::searchAssignable()`, prefix-matched on name or short form, capped at 50.

Two consequences worth knowing:

- The endpoint returns **active designations only**, while the rendered `designations` prop still
  seeds the control. That is what keeps a retired title visible for the user who holds it while
  making it unassignable to anyone else — the same rule as above, arrived at from the other side.
- The seeded options are shown until the first remote result lands, so opening the menu never blinks
  empty for the length of the debounce.

`DesignationService::filterOptions()` is deliberately different — it lists *every* designation,
retired ones included, because a retired title still has holders and an admin has to be able to find
them.

### 8.4 `users.designation_id` is nullable in the database and required in the form

The column is nullable; `EmployeeValidationRules::employeeRules()` makes it `required`.

`users` already had rows when designations arrived. Backfilling them would have meant inventing an
"Unassigned" designation that exists only to satisfy a constraint and then shows up in every picker
and filter forever. Instead, pre-existing rows keep a null title and render as "—", while every user
created or edited since must be given one — and the omission is reported as a field error rather
than a driver exception. Both halves are pinned by tests.

The designation column on the user list is **not sortable**. Ordering by it means ordering by a
joined column, a query shape this list does not have; filter instead. The index reasoning for that
filter is in [§2.1.1](#211-indexes--every-one-of-them-measured) — the answer was "no new index".

### 8.5 Seeding

`Database\Seeders\Admin\DesignationSeeder` ships a **placeholder** list of garments-manufacturing
titles and is called from `DatabaseSeeder`. It is idempotent (`firstOrCreate` on the unique name), so
it will not overwrite a title renamed through the UI. **Replace the array with the real HR list.**

---

## 9. `RecordStatus` is the one active/inactive vocabulary

`App\Enums\RecordStatus` (`'A'` / `'I'`) plus `App\Concerns\HasStatus` is how every table in this
application says "in use" or "retired". A model gets the cast, `scopeActive()`, `scopeInactive()` and
`isActive()` from a single `use` — that trait is what makes the enum *usable* rather than merely
shared, and is why the four methods are not copy-pasted per table.

It sits at the root of `app/Enums/` because it belongs to no module, the same reasoning as `Theme`.

**It is named `RecordStatus`, not `Status`, deliberately.** A BQS or purchase order moving through
Draft → Approved → Cancelled is a different concept with a different lifecycle and belongs in its own
module-scoped enum. Leaving `Status` unclaimed gives that enum an obvious home that is not this file.

### 9.1 `users.approved` became `users.status`

`users` carried a boolean `approved` until `RecordStatus` became the convention; at that point it was
the only boolean active flag left, so it moved
(`2026_08_27_131928_change_users_approved_to_status`). The column was added, backfilled from
`approved`, and the old one dropped.

**The index moved with it.** `users_deleted_at_approved_name_index` became
`users_deleted_at_status_name_index` on `(deleted_at, status, name)`, and was **re-measured** rather
than assumed: the planner picks it on every run of `users:benchmark` at 0.28–0.47 ms, against the
0.25 ms the boolean version recorded. The index survived the column change intact —
[§2.1.1](#211-indexes--every-one-of-them-measured).

The migration drops the index *before* the column: SQLite rebuilds the table on a column drop and
would otherwise carry a dangling index definition into the copy.

### 9.2 `approval_authority` is still a boolean, and stays one

It sits in the same fieldset and is not the same kind of thing. `status` says whether an account may
be used; `approval_authority` says what its holder may do. Merging them would conflate "disabled"
with "cannot approve", which are independent. See
[§2.2](#22-approval_authority-is-not-wired-to-anything) — it is still wired to nothing.

`users.all_buyer_access` ([§10](#10-buyers-and-buyer-scoped-access)) is the second such flag, and
unlike `approval_authority` it *is* wired to something: the buyer scope reads it on every query.

---

## 10. Buyers and buyer-scoped access

The mechanism, its rules and the reasoning behind each one live in
[ARCHITECTURE.md §9.2](../ARCHITECTURE.md#92-buyer-scoped-access-control). This section covers only
what the *surfaces* do.

### 10.1 The requirement asked for a background job, and it was not built

The original request was: a `buyers` table, per-user buyer access, an "all buyer access" state, and a
background action that copies each newly created buyer into an access row for every all-access user.

The first three shipped. **The fourth was removed** — deliberately, not by omission:

- **Revocation becomes lossy.** Once the wildcard is materialised, a row it created is
  indistinguishable from one an administrator chose. Revoking all-access can no longer tell which
  rows to keep, so it either destroys deliberate grants or leaves rows nobody intended.
- **It needs a second job to be correct.** New buyer → existing all-access users is only half of it;
  a user *newly granted* all-access needs the mirror-image backfill, and the two must agree forever.
- **Its failure mode is invisible.** `QUEUE_CONNECTION=database`, and the worker only runs while
  `composer run dev` does. A missed job produces a buyer that exists but that nobody can see, which
  reads as a permissions bug and is diagnosed as one.
- **It buys nothing.** A flag the scope short-circuits on gives the same visible behaviour with no
  job, no backfill, no reconciliation and no worker to monitor.

So `users.all_buyer_access` is the grant, `BuyerAccessService::assign()` clears the pivot when it goes
on, and a buyer created a second from now is visible immediately because nothing had to happen.

### 10.2 The buyers screen

`pages/admin/buyers/index.tsx`, the designation shape: one page, create/edit/delete in modals, no
Active/Historical tabs. `name` is unique and required; `code` is unique *when present* and optional,
because rows carried over from the old system arrive without one. `name` filters as `Contains` and
`code` as `Prefix` — [ARCHITECTURE.md §6.3](../ARCHITECTURE.md#63-migrations) governs which is which.

The **Granted** column counts pivot rows, so it deliberately excludes all-access users, who hold
none. It is labelled "Granted" rather than "Users" for exactly that reason, and its tooltip says so.

`BuyerService::deletionBlocker()` currently returns `null`: no buyer-owned table exists yet. Access
rows do **not** block a delete — they cascade, being derived permissions rather than history. Every
table that records a *fact* about a buyer adds its check there as Merchandising and Production land.

### 10.3 Access is edited on the users screen

There is no buyer-access page. `admin/users` gains a Buyers column (`All` / a count / `— none —`) and
a dialog beside the roles one, posting to `admin.users.buyer-access`.

- The column and the dialog's data are gated by `admin.buyer-access.view`; the write by
  `admin.buyer-access.update`. The users list eager-loads `buyers` and `withCount` **only** for
  viewers holding the former, so the props are absent rather than zero for everyone else — a `0` from
  a relation that was never loaded is a lie.
- Two guards, in `BuyerAccessService` rather than a policy for the reason
  [§2.5](#25-the-four-escalation-guards) already records — `Gate::before` bypasses a policy for
  exactly the account a privilege guard binds:
  **you cannot edit your own buyer access**, and **you cannot grant access you do not hold yourself**
  (neither the wildcard, nor a buyer you cannot see).
- Ticking **All buyers** disables the picker rather than ignoring it, because the server clears the
  pivot in that case and a picker that appears to still matter would be lying.

### 10.4 Zero buyers is a valid state

A user with no grants and no flag sees nothing, which is correct for a new hire pending assignment.
Buyer-scoped lists render `components/shared/no-buyer-access.tsx` — "no buyers assigned, contact an
administrator" — instead of an empty table, so "you have no access" never reads as "there is no
data". That component exists before its first consumer on purpose: the alternative is each new list
inventing its own empty state.
