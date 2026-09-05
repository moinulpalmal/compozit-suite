<?php

namespace App\Listeners\Admin;

use App\Enums\Admin\AuditEvent;
use App\Models\User;
use App\Services\Admin\AuditRecorder;
use Illuminate\Auth\Events\Login;

/**
 * Records a successful sign-in — ARCHITECTURE.md §9.3.
 *
 * A sign-in changes no row, so nothing about it reaches the trail through model
 * events. It is recorded against the {@see User} who signed in, which is what
 * puts it on that account's record history beside everything else done to them.
 *
 * Registered in `AppServiceProvider::configureAuditListeners()` rather than
 * discovered: explicit registration matches how `Gate::before` and the morph map
 * are wired, and there is exactly one place to look for what listens to what.
 */
class RecordLogin
{
    public function __construct(private readonly AuditRecorder $audits) {}

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->audits->record($event->user, AuditEvent::LoggedIn, [], [
            'employee_id' => $event->user->employee_id,
            'guard' => $event->guard,
            'remember' => $event->remember,
        ]);
    }
}
