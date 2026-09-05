<?php

namespace App\Listeners\Admin;

use App\Enums\Admin\AuditEvent;
use App\Models\User;
use App\Services\Admin\AuditRecorder;
use Illuminate\Auth\Events\Logout;

/**
 * Records a sign-out — ARCHITECTURE.md §9.3.
 *
 * `Logout::$user` is nullable: the event also fires for a guard with nobody
 * signed in, and there is nothing to record then. A session that simply expires
 * fires no event at all, so the trail shows sign-ins without a matching sign-out
 * — which is a property of how sessions end, not a gap in the recording.
 */
class RecordLogout
{
    public function __construct(private readonly AuditRecorder $audits) {}

    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audits->record($event->user, AuditEvent::LoggedOut, [], [
            'employee_id' => $event->user->employee_id,
            'guard' => $event->guard,
        ]);
    }
}
