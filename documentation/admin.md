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
| c. Buyer-wise user access control | `Admin\BuyerAccessController` | `pages/admin/buyer-access/` | 🟡 scaffolded |
| d. Buyer setup & management | `Admin\BuyerController` | `pages/admin/buyers/` | 🟡 scaffolded |
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
| `approved` | `boolean`, default `true` | Active/inactive switch surfaced in the edit modal. |
| `approval_authority` | `boolean`, default `false` | **Parked.** See [§2.2](#22-approval-authority-is-not-wired-to-anything). |
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
| `users_email_unique` | login-adjacent lookups, availability check, email prefix search **and** email sort | unique constraint |
| `users_employee_id_unique` | login, availability check, employee-ID prefix search **and** ID sort | unique constraint |
| `users_inserted_by_foreign` | FK integrity | InnoDB, automatic |
| `users_last_updated_by_foreign` | FK integrity | InnoDB, automatic |
| `users_deleted_at_name_index` | default view, name sort, name prefix search, both counts | `…091435_add_indexes_to_users_table` |
| `users_deleted_at_created_at_index` | "Added" sort | `…094614_add_data_table_indexes_to_users_table` |
| `users_deleted_at_personal_mobile_index` | mobile prefix search | same |
| `users_deleted_at_official_mobile_index` | mobile prefix search | same |
| `users_deleted_at_extension_index` | extension prefix search | same |
| `users_deleted_at_approved_name_index` | status filter + default sort | same |
| `users_designation_id_foreign` | FK integrity **and** the designation filter | InnoDB, automatic |

Every composite leads with `deleted_at` because every query on this table has it — the soft-delete
scope is always applied.

**Measured at 5,000 rows on MySQL 9.6** (`php artisan users:benchmark`, median of five runs):

| Query | Before | After | Index |
| --- | --- | --- | --- |
| Sort by "Added" (`created_at desc`) | **11.34 ms** + filesort | **0.33 ms** | `…created_at_index` |
| Extension lookup (selective prefix) | 6.65 ms | **0.19 ms** | `…extension_index` |
| Mobile lookup (selective prefix) | 6.65 ms | **0.28 ms** | `…personal_mobile_index` |
| Inactive-user filter | 1.25 ms | **0.25 ms** | `…approved_name_index` |

**Rejected candidates — do not re-add without new measurements:**

| Candidate | Why not |
| --- | --- |
| `(deleted_at, employee_id)` | Sorting by employee ID was **slower** with it: 0.42 ms → 0.95 ms. MySQL already walks `users_employee_id_unique` in order and stops at the page limit. |
| `(deleted_at, email)` | Same story via `users_email_unique`; 0.56 ms → 0.47 ms is inside the noise. |
| `(deleted_at, gender, name)` | Gender has three values — not selective. Both plans sub-millisecond (0.35 ms → 0.29 ms). |
| `(deleted_at, designation_id, name)` | **The planner never chose it.** With the composite installed, the rare-designation filter still planned on `users_designation_id_foreign + filesort` at 1.58 ms — indistinguishable from the 1.29–1.66 ms it runs at without. The FK index `constrained()` already creates is enough, exactly as [§6.3](../ARCHITECTURE.md#63-migrations) predicts. |

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

### 2.1.2 Search is prefix-matched and field-scoped

`User::scopeSearch()` takes a **field** and a term, and matches `LIKE 'term%'`.

Both halves of that are load-bearing:

- **Prefix, not contains.** `LIKE '%term%'` cannot use a B-tree, ever. `LIKE 'term%'` is a range
  scan. So "158" finds employee 15868 and "868" does not — that is the contract, and the empty
  state and the search placeholder both say so.
- **One field, not an `OR` across all six.** An `OR` over six columns is the case MySQL handles
  worst: it either attempts an unreliable index merge or picks one index and filters the rest. With
  a single search box, the mobile and extension indexes above would have been built and then never
  used. The field selector is what makes them pay.

The term is escaped with `addcslashes($term, '%_\\')` so a user typing `%` gets a literal match
rather than a full-table wildcard scan. There is a test pinning that.

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
| `filter` | `active` (default) / `trashed` |
| `sort` | `name` (default), `employee_id`, `email`, `created_at` — `User::SORTABLE` |
| `direction` | `asc` (default) / `desc` |
| `search_field` | one of `User::SEARCHABLE`; `name` by default |
| `search` | prefix term, max 100 chars |
| `gender` | `M` / `F` / `O`, empty for all |
| `status` | `active` / `inactive`, empty for all |
| `page` | paginator page, 25 per page |

`UserIndexRequest` validates all of it and `UserIndexRequest::filters()` applies the defaults, so the
controller never sees a half-specified state. Changing any filter resets to page 1 — staying on
page 9 of a result set that now has two pages would show an empty table.

**Sort and search field names are allow-listed twice**, in the request and again in the model scope.
That is not redundancy for its own sake: passing request input to `orderBy()` is a SQL injection,
and the scope is the layer that guarantees it cannot happen even if the query is ever built from
somewhere other than this controller. `UserTest` pins it with `?sort=name; DROP TABLE users`.
This deliberately diverges from the `index/create/edit` pattern that roles and permissions use —
see [ARCHITECTURE.md → Module 1](../ARCHITECTURE.md#5-module-registry). Do not harmonise one into
the other without a new decision.

### 3.2 Components

| Component | Purpose |
| --- | --- |
| `admin/user-form-dialog.tsx` | Create and edit. Password and role fields render only when creating. Also exports `PasswordStrength`. |
| `admin/user-role-dialog.tsx` | Replaces a user's role set wholesale. |
| `admin/user-password-dialog.tsx` | Sets another user's password — no current-password prompt, because the actor is not the owner. |
| `admin/confirm-action-dialog.tsx` | Generic confirm-then-submit over a Wayfinder `.form()`. Takes the trigger as `children`. |
| `admin/confirm-delete-dialog.tsx` | The destructive icon-button preset over the above. Roles and permissions still use it unchanged. |
| `admin/users-table-toolbar.tsx` | Gender and status filters, plus the field-scoped search box. |
| `admin/pagination.tsx` | Previous/next paging. Moves to `components/shared/` the moment a second module imports it, per [ARCHITECTURE.md §6.5](../ARCHITECTURE.md#65-components). |

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
6. `admin/user-form-dialog.tsx` → a `<Field>` in the grid.
7. `UserFactory`, then a test in `tests/Feature/Admin/UserTest.php`.
8. Update the table in [§2.1](#21-the-users-table).

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

`designations.status` (`'A'`/`'I'`) and `users.approved` (boolean) say the same thing two different
ways. **This is a deliberate owner decision**, not drift — the char flag matches the HR system the
data comes from. Do not "harmonise" it into a boolean, and equally **do not copy it onto a third
table** on the assumption that it is now the house style. `users.approved` remains the precedent for
anything without that HR lineage.

The consequence is that this screen has **two verbs where `users` has one**:

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
