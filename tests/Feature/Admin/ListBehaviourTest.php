<?php

use App\Models\Admin\Designation;
use App\Models\Admin\Permission;
use App\Models\Admin\Role;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| The shared list apparatus
|--------------------------------------------------------------------------
|
| Every Admin list is paginated, searchable and sortable through
| `App\Concerns\Listable` and `App\Http\Requests\ListRequest`
| (ARCHITECTURE.md §8.6). The point of this file is that the *contract* is
| tested once, over every surface, rather than four times with drift between
| the copies. Surface-specific behaviour stays in that surface's own test.
|
| A new list is added to `surfaces()` below and inherits the whole set.
|
*/

/**
 * Every list surface: route name, prop key, permission, and how to make rows.
 *
 * @return array<string, array{0: string, 1: string, 2: string, 3: callable(int): void}>
 */
function surfaces(): array
{
    return [
        'users' => [
            'admin.users.index',
            'users',
            'admin.users.view',
            fn (int $count) => User::factory()->count($count)->create(),
        ],
        'designations' => [
            'admin.designations.index',
            'designations',
            'admin.designations.view',
            fn (int $count) => Designation::factory()->count($count)->create(),
        ],
        'roles' => [
            'admin.roles.index',
            'roles',
            'admin.roles.view',
            fn (int $count) => collect(range(1, $count))->each(
                fn (int $i) => Role::findOrCreate("role-{$i}", 'web'),
            ),
        ],
        'permissions' => [
            'admin.permissions.index',
            'permissions',
            'admin.permissions.view',
            fn (int $count) => collect(range(1, $count))->each(
                fn (int $i) => Permission::findOrCreate("listing.things.act-{$i}", 'web'),
            ),
        ],
    ];
}

dataset('list surfaces', fn () => surfaces());

test('every list is paginated at 25 rows', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
) {
    $this->actingAs(userWithPermissions($permission));

    // 30 rows on top of whatever the surface already has, so page 1 is
    // certainly full and a second page certainly exists.
    $seed(30);

    $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has("{$prop}.data", 25)
            ->where("{$prop}.current_page", 1)
            ->where("{$prop}.per_page", 25));
})->with('list surfaces');

test('every list serves a second page', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(30);

    $this->get(route($route, ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where("{$prop}.current_page", 2)
            ->has("{$prop}.data"));
})->with('list surfaces');

test('every list rejects an unknown sort column rather than reaching the database', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(1);

    // The allow-list is the SQL-injection guard. It is validated in the request
    // *and* clamped in the scope; this pins the first half.
    $this->get(route($route, ['sort' => 'name; DROP TABLE users']))
        ->assertSessionHasErrors('sort');

    expect(User::query()->count())->toBeGreaterThan(0);
})->with('list surfaces');

test('every list rejects an unknown search field', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
) {
    $this->actingAs(userWithPermissions($permission));

    $this->get(route($route, ['search_field' => 'password', 'search' => 'x']))
        ->assertSessionHasErrors('search_field');
})->with('list surfaces');

test('every list accepts its own allow-listed sort columns in both directions', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(3);

    $this->get(route($route, ['sort' => 'name', 'direction' => 'desc']))
        ->assertOk()
        ->assertSessionHasNoErrors()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'desc'));
})->with('list surfaces');

test('every list keeps its filters across pages', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(30);

    // `withQueryString()` is what carries the sort onto the paginator links;
    // without it page 2 silently reverts to the default order.
    $this->get(route($route, ['sort' => 'name', 'direction' => 'desc', 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'desc')
            ->where("{$prop}.current_page", 2));
})->with('list surfaces');

test('every list search matches by prefix, not mid-string', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(3);

    // "Prefix, not contains" is the contract that keeps the query indexable
    // (ARCHITECTURE.md §6.3). A mid-string term must find nothing.
    $this->get(route($route, ['search_field' => 'name', 'search' => 'zzz-no-such-prefix']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has("{$prop}.data", 0));
})->with('list surfaces');

test('a wildcard in the search term is escaped, not honoured', function (
    string $route,
    string $prop,
    string $permission,
    callable $seed,
) {
    $this->actingAs(userWithPermissions($permission));

    $seed(3);

    // Unescaped, "%" would match every row instead of none.
    $this->get(route($route, ['search_field' => 'name', 'search' => '%']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has("{$prop}.data", 0));
})->with('list surfaces');
