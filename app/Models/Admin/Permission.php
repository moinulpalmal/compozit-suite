<?php

namespace App\Models\Admin;

use App\Concerns\Listable;
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
     * The fields the permission list may be searched by.
     *
     * A prefix match on `name` doubles as the module filter's manual escape
     * hatch: searching `merchandising.` narrows to that module.
     *
     * @var list<string>
     */
    public const array SEARCHABLE = ['name'];

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
     * Matched as a prefix on `name` rather than against a stored column: the
     * module is not a column, it is the first dot-delimited segment, and a
     * prefix is the one shape an index can serve (ARCHITECTURE.md §6.3).
     *
     * @param  Builder<Permission>  $query
     */
    public function scopeInModule(Builder $query, ?string $module): void
    {
        $module = trim((string) $module);

        if ($module === '') {
            return;
        }

        $query->where('name', 'like', addcslashes($module, '%_\\').'.%');
    }
}
