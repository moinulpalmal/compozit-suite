<?php

namespace App\Models\Admin;

use App\Concerns\Listable;
use App\Enums\FilterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * The application's permission model.
 *
 * Extends spatie/laravel-permission's permission so permissions live under
 * the Admin module like every other Admin-owned model. Bound through
 * `config('permission.models.permission')`.
 *
 * Names follow `{module}.{resource}.{action}` — see ARCHITECTURE.md §9.1.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, Role> $roles
 */
class Permission extends SpatiePermission
{
    use Listable;

    /**
     * The columns the permission list's filter row exposes.
     *
     * `module` is not a column. It is the first dot-delimited segment of `name`,
     * so its cell is a {@see FilterType::Scope} that hands the value to
     * {@see self::scopeModule()}.
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'name' => FilterType::Contains,
        'module' => FilterType::Scope,
    ];

    /**
     * The columns the permission list may be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'created_at'];

    /**
     * The module segment of the permission name.
     */
    public function module(): string
    {
        return explode('.', $this->name)[0];
    }

    /**
     * Limit the query to one module segment.
     *
     * Named for its filter key so {@see FilterType::Scope} can resolve it —
     * `filter[module]` calls `scopeModule()`.
     *
     * Matched as a prefix on `name` rather than against a stored column: the
     * module is not a column, it is the first dot-delimited segment, and a
     * prefix is the one shape an index can serve (ARCHITECTURE.md §6.3).
     *
     * @param  Builder<Permission>  $query
     */
    public function scopeModule(Builder $query, ?string $module): void
    {
        $module = trim((string) $module);

        if ($module === '') {
            return;
        }

        $query->where('name', 'like', addcslashes($module, '%_\\').'.%');
    }
}
