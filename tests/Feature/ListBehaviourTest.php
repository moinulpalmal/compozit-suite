<?php

use App\Enums\RecordStatus;
use App\Http\Requests\ListRequest;
use App\Models\Admin\Buyer;
use App\Models\Admin\Designation;
use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\Merchandising\PurchaseOrder;
use App\Models\Settings\NotificationColor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| The shared list apparatus
|--------------------------------------------------------------------------
|
| Every list is paginated, sortable, and filtered by a row of cells under its
| headers, through `App\Concerns\Listable` and `App\Http\Requests\ListRequest`
| (ARCHITECTURE.md §8.6). The point of this file is that the *contract* is
| tested once, over every surface, rather than six times with drift between the
| copies. Surface-specific behaviour stays in that surface's own test.
|
| A new list is added to `surfaces()` below and inherits the whole set.
|
| **This file sits at the root of `tests/Feature/` rather than under a module
| directory**, for the same reason `ListRequest` sits at the root of
| `app/Http/Requests/`: it tests apparatus that belongs to no module. It lived
| in `tests/Feature/Admin/` while every surface was an Admin one, and moved when
| Settings' notification colours joined the dataset.
|
| The last two tests are deliberately single-surface: prefix matching and
| combining two cells need a surface that actually has a prefix column and a
| second cell, and only `users` and `designations` do. They are still tests of
| the shared apparatus, which is why they live here.
|
*/

/** A token no seeded row will contain by accident, used to pin one row. */
const FILTER_TOKEN = 'Qqzz';

/**
 * Every list surface: route name, prop key, permission, how to make N rows, how to
 * make one row carrying a given token, and the two columns the shared cases drive.
 *
 * The last two used to be hard-coded as `name`, which held while every surface had a
 * `name` column. Purchase orders do not — an order is identified by its number, and
 * the column worth finding mid-string is the vendor. Naming them per surface is what
 * lets a list with different columns still inherit the whole contract.
 *
 * @return array<string, array{0: string, 1: string, 2: string, 3: callable(int): void, 4: callable(string): void, 5: string, 6: string}>
 */
function surfaces(): array
{
    return [
        'users' => [
            'admin.users.index',
            'users',
            'admin.users.view',
            fn (int $count) => User::factory()->count($count)->create(),
            fn (string $name) => User::factory()->create(['name' => $name]),
            'name',
            'name',
        ],
        'designations' => [
            'admin.designations.index',
            'designations',
            'admin.designations.view',
            fn (int $count) => Designation::factory()->count($count)->create(),
            fn (string $name) => Designation::factory()->create(['name' => $name]),
            'name',
            'name',
        ],
        'buyers' => [
            'admin.buyers.index',
            'buyers',
            'admin.buyers.view',
            fn (int $count) => Buyer::factory()->count($count)->create(),
            fn (string $name) => Buyer::factory()->create(['name' => $name]),
            'name',
            'name',
        ],
        'roles' => [
            'admin.roles.index',
            'roles',
            'admin.roles.view',
            fn (int $count) => collect(range(1, $count))->each(
                fn (int $i) => Role::findOrCreate("role-{$i}", 'web'),
            ),
            fn (string $name) => Role::findOrCreate($name, 'web'),
            'name',
            'name',
        ],
        'permissions' => [
            'admin.permissions.index',
            'permissions',
            'admin.permissions.view',
            fn (int $count) => collect(range(1, $count))->each(
                fn (int $i) => Permission::findOrCreate("listing.things.act-{$i}", 'web'),
            ),
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            'name',
            'name',
        ],
        /*
         * The only non-Admin surface, and the reason this file is no longer an
         * Admin one. It is also the surface proving `settings.master-data.view`
         * gates a list the same way an `admin.*` permission does.
         */
        'notification colours' => [
            'settings.master-data.notification-colors.index',
            'notificationColors',
            'settings.master-data.view',
            fn (int $count) => NotificationColor::factory()->count($count)->create(),
            fn (string $name) => NotificationColor::factory()->create(['name' => $name]),
            'name',
            'name',
        ],
        /*
         * The first buyer-scoped surface, and the first with no `name` column.
         * Its seeds also grant the acting user access to the buyer they create —
         * without that `BuyerScope` filters every row away and each shared case
         * fails on an empty list, which reads as a pagination bug and is not one.
         */
        'purchase orders' => [
            'merchandising.purchase-orders.index',
            'purchaseOrders',
            'merchandising.purchase-orders.view',
            fn (int $count) => seedPurchaseOrders($count),
            fn (string $name) => seedPurchaseOrders(1, $name),
            'po_number',
            'vendor_name',
        ],
    ];
}

/**
 * Create purchase orders the acting user can actually see.
 *
 * `PurchaseOrder` is `BuyerScoped` (ARCHITECTURE.md §9.2), so rows are invisible
 * unless the signed-in user holds their buyer. Every shared case signs in before it
 * seeds, so the grant can be made here rather than in each one.
 */
function seedPurchaseOrders(int $count, ?string $vendorName = null): void
{
    $buyer = Buyer::factory()->create();

    PurchaseOrder::factory()
        ->count($count)
        ->create([
            'buyer_id' => $buyer->id,
            ...($vendorName === null ? [] : ['vendor_name' => $vendorName]),
        ]);

    Auth::user()?->buyers()->syncWithoutDetaching([$buyer->id]);
}

dataset('list surfaces', fn () => surfaces());

test('every list is paginated at 10 rows by default', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    // 30 rows on top of whatever the surface already has, so page 1 is
    // certainly full and a second page certainly exists.
    $seed(30);

    $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has("{$prop}.data", ListRequest::DEFAULT_PER_PAGE)
            ->where("{$prop}.current_page", 1)
            ->where("{$prop}.per_page", ListRequest::DEFAULT_PER_PAGE));
})->with('list surfaces');

test('every list serves a second page', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(30);

    $this->get(route($route, ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where("{$prop}.current_page", 2)
            ->has("{$prop}.data"));
})->with('list surfaces');

test('every list honours an allow-listed page size', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(30);

    /*
     * The probe must not be `DEFAULT_PER_PAGE`, or the test passes even when
     * `per_page` is ignored entirely. 50 is the smallest option that is not the
     * default and still holds the whole seeded set on one page.
     */
    $this->get(route($route, ['per_page' => 50]))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn ($page) => $page
            ->where("{$prop}.per_page", 50)
            ->where('filters.per_page', 50)
            ->where("{$prop}.last_page", 1));
})->with('list surfaces');

test('every list refuses a page size outside the allow-list', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    // Unclamped, this is a denial-of-service that costs nothing to send.
    $this->get(route($route, ['per_page' => 999999]))
        ->assertSessionHasErrors('per_page');
})->with('list surfaces');

test('every list rejects an unknown sort column rather than reaching the database', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(1);

    // The allow-list is the SQL-injection guard. It is validated in the request
    // *and* clamped in the scope; this pins the first half.
    $this->get(route($route, ['sort' => 'name; DROP TABLE users']))
        ->assertSessionHasErrors('sort');

    expect(User::query()->count())->toBeGreaterThan(0);
})->with('list surfaces');

test('every list rejects an unknown filter column', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    // A column outside `FILTERABLE` must be an error, not a silent ignore —
    // otherwise a typo'd filter looks like a filter that found nothing.
    $this->get(route($route, ['filter' => ['password' => 'x']]))
        ->assertSessionHasErrors('filter');
})->with('list surfaces');

test('every list accepts its own allow-listed sort columns in both directions', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(3);

    $this->get(route($route, ['sort' => $sortColumn, 'direction' => 'desc']))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', $sortColumn)
            ->where('filters.direction', 'desc'));
})->with('list surfaces');

test('every list keeps its sort and page size across pages', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(30);

    /*
     * `withQueryString()` is what carries these onto the paginator links;
     * without it page 2 silently reverts to the defaults. 25 rather than
     * `DEFAULT_PER_PAGE`, so a reverted page size is visible here.
     */
    $this->get(route($route, [
        'sort' => $sortColumn,
        'direction' => 'desc',
        'per_page' => 25,
        'page' => 2,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', $sortColumn)
            ->where('filters.direction', 'desc')
            ->where('filters.per_page', 25)
            ->where("{$prop}.per_page", 25)
            ->where("{$prop}.current_page", 2)
            ->has("{$prop}.data"));
})->with('list surfaces');

test('every list has a contains column that matches mid-string', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(3);
    $seedNamed('alpha-'.FILTER_TOKEN.'-omega');

    // The contract reversed here: every surface declares one FilterType::Contains
    // column, so a mid-string term must find the row. This costs the index —
    // see App\Enums\FilterType.
    $this->get(route($route, ['filter' => [$containsColumn => FILTER_TOKEN]]))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn ($page) => $page->has("{$prop}.data", 1));
})->with('list surfaces');

test('a wildcard in a filter value is escaped, not honoured', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
    callable $seedNamed,
    string $sortColumn,
    string $containsColumn,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(3);
    $seedNamed('alpha-'.FILTER_TOKEN.'-omega');

    // Unescaped under `LIKE '%term%'`, "%" would match every row instead of
    // only rows containing a literal percent sign.
    $this->get(route($route, ['filter' => [$containsColumn => '%']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has("{$prop}.data", 0));
})->with('list surfaces');

test('a prefix column does not match mid-string', function () {
    $this->actingAs(userWithPermissions('admin.users.view'));

    User::factory()->create(['employee_id' => '15868']);

    // `employee_id` is FilterType::Prefix precisely so it stays indexable, and
    // that choice is visible to the user: "158" finds it, "868" does not.
    $this->get(route('admin.users.index', ['filter' => ['employee_id' => '158']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 1));

    $this->get(route('admin.users.index', ['filter' => ['employee_id' => '868']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('users.data', 0));
});

test('two filter cells combine with AND', function () {
    $this->actingAs(userWithPermissions('admin.designations.view'));

    Designation::factory()->create(['name' => FILTER_TOKEN.'-active', 'status' => RecordStatus::Active]);
    Designation::factory()->create(['name' => FILTER_TOKEN.'-inactive', 'status' => RecordStatus::Inactive]);

    // AND, not OR. This is what keeps a filter row indexable at all: the
    // leading predicate can use an index and the rest are residual filters.
    $this->get(route('admin.designations.index', [
        'filter' => ['name' => FILTER_TOKEN, 'status' => RecordStatus::Active->value],
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('designations.data', 1));
});
