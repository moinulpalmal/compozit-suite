<?php

namespace App\Models\Admin;

use App\Concerns\Audited;
use App\Concerns\Listable;
use App\Contracts\Auditable;
use App\Enums\FilterType;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The application's role model.
 *
 * Extends spatie/laravel-permission's role so roles live under the Admin
 * module like every other Admin-owned model. Bound through
 * `config('permission.models.role')`.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, Permission> $permissions
 */
class Role extends SpatieRole implements Auditable
{
    use Audited, Listable;

    /**
     * The role that bypasses every permission check.
     *
     * @see AppServiceProvider::configureAuthorization()
     */
    public const string SUPER_ADMIN = 'super-admin';

    /**
     * The columns the role list's filter row exposes.
     *
     * `users_count` and `permissions_count` have no cell for the same reason
     * they have no sort: they are `withCount` aggregates, so filtering them
     * needs `HAVING` rather than `WHERE`.
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = ['name' => FilterType::Contains];

    /**
     * The columns the role list may be sorted by.
     *
     * `users_count` and `permissions_count` are absent deliberately: they are
     * aggregates, not columns, so sorting by them needs `orderBy` on the
     * `withCount` alias rather than the allow-list path. Add them only with a
     * measurement behind it.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'created_at'];

    /**
     * Determine whether this role bypasses every permission check.
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }
}
