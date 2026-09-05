<?php

namespace App\Listeners\Admin;

use App\Enums\Admin\AuditEvent;
use App\Models\User;
use App\Services\Admin\AuditRecorder;
use Illuminate\Auth\Events\Failed;

/**
 * Records a rejected sign-in — ARCHITECTURE.md §9.3.
 *
 * The one audit event that routinely has **no record to attach to**: a wrong
 * employee ID matches no user, and refusing to record it would hide exactly the
 * attempts most worth seeing. `AuditRecorder` writes those with a null morph,
 * which is what `nullableMorphs()` in the migration exists for.
 */
class RecordFailedLogin
{
    /**
     * The only credential field this listener may read.
     *
     * `Failed::$credentials` carries the submitted **password** alongside the
     * identifier. Passing the array through would write a plaintext password into
     * `audit_logs` — the exact failure `User::$auditExclude` prevents for the
     * hash, arriving by a different door. Name the one field wanted; never pass
     * the array.
     *
     * The identifier is `employee_id` rather than `email` because
     * `config('fortify.username')` is `employee_id` (ARCHITECTURE.md §9.6).
     */
    private const string IDENTIFIER = 'employee_id';

    public function __construct(private readonly AuditRecorder $audits) {}

    /**
     * Handle the event.
     */
    public function handle(Failed $event): void
    {
        $identifier = $event->credentials[self::IDENTIFIER] ?? null;

        $this->audits->record(
            $event->user instanceof User ? $event->user : null,
            AuditEvent::LoginFailed,
            [],
            [
                'employee_id' => is_scalar($identifier) ? (string) $identifier : null,
                'guard' => $event->guard,
                /*
                 * Whether the identifier exists at all. A wrong password on a real
                 * account and a guess at an account that does not exist are very
                 * different events, and the row otherwise looks identical.
                 */
                'user_exists' => $event->user instanceof User,
            ],
        );
    }
}
