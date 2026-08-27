<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps who created and who last changed a user.
 *
 * `inserted_by` and `last_updated_by` are never mass-assignable and are never
 * set by hand — they are written here so every write path (Admin screens,
 * account settings, console) is stamped the same way.
 *
 * Writes with no authenticated actor (seeders, migrations, queued jobs) leave
 * the columns null, which is why both foreign keys are nullable.
 */
class UserObserver
{
    /**
     * Handle the User "creating" event.
     */
    public function creating(User $user): void
    {
        $user->inserted_by ??= Auth::id();
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        if (Auth::hasUser()) {
            $user->last_updated_by = Auth::id();
        }
    }
}
