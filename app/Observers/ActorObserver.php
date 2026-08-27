<?php

namespace App\Observers;

use App\Models\Admin\Designation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps who created and who last changed a record.
 *
 * Registered with `#[ObservedBy(ActorObserver::class)]` on every model carrying
 * `inserted_by` and `last_updated_by` — currently {@see User} and
 * {@see Designation}.
 *
 * Neither column is mass-assignable on any of them, and neither is ever set by
 * hand: this class is the only writer, so *every* write path — Admin screens,
 * account settings, console — is stamped identically. It is one shared class
 * rather than one observer per model precisely because "identically" stops
 * being true the moment a second copy is edited on its own.
 *
 * Writes with no authenticated actor (seeders, migrations, queued jobs) leave
 * the columns null, which is why both foreign keys are nullable.
 */
class ActorObserver
{
    /**
     * Handle the "creating" event for any stamped model.
     */
    public function creating(Model $model): void
    {
        $model->inserted_by ??= Auth::id();
    }

    /**
     * Handle the "updating" event for any stamped model.
     */
    public function updating(Model $model): void
    {
        if (Auth::hasUser()) {
            $model->last_updated_by = Auth::id();
        }
    }
}
