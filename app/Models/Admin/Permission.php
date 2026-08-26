<?php

namespace App\Models\Admin;

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
    /**
     * The module segment of the permission name.
     */
    public function module(): string
    {
        return explode('.', $this->name)[0];
    }
}
