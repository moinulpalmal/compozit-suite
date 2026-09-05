<?php

namespace App\Services\Admin;

use App\Concerns\Audited;
use App\Contracts\Auditable;
use App\Enums\Admin\AuditEvent;
use App\Models\Admin\AuditLog;
use App\Models\User;
use App\Observers\ActorObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

/**
 * Records the things Eloquent events cannot see — ARCHITECTURE.md §9.3.
 *
 * The package covers `created` / `updated` / `deleted` / `restored` on a model
 * *instance*. Three kinds of change in this application never go through one:
 *
 * - **Pivot writes.** `spatie/laravel-permission` and `BuyerAccessService` write
 *   `model_has_roles`, `role_has_permissions` and `buyer_user` as raw pivot rows.
 *   These are the escalation-sensitive actions `documentation/admin.md` §2.5
 *   guards, so a trail that misses them misses the point of having one.
 * - **Query-builder bulk updates.** The importers retire revisions with
 *   `Builder::update()`, which fires no model event at all.
 * - **Authentication.** A sign-in changes no row.
 *
 * **This is the only writer for all three**, for the reason
 * {@see ActorObserver} is one shared class: "recorded
 * identically" stops being true the moment there is a second copy of the logic.
 *
 * It deliberately does **not** wrap ordinary model saves. Those are already
 * audited, and recording them here as well would double every row.
 */
final class AuditRecorder
{
    /**
     * Record something that happened, whether or not it changed a row.
     *
     * Pass the record it concerns as `$subject` where there is one — the audit is
     * then written through the package, so it picks up the buyer tag, the actor
     * name and the IP/URL/agent resolvers exactly like every other row. Pass null
     * only when there genuinely is no record, which today means a rejected
     * sign-in for an employee ID matching no user.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(?Model $subject, AuditEvent $event, array $old = [], array $new = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        if ($subject instanceof Auditable) {
            $this->recordThroughPackage($subject, $event, $old, $new);

            return;
        }

        $this->recordWithoutSubject($event, $old, $new);
    }

    /**
     * Record a change, and nothing at all when there was no change.
     *
     * The grant call sites all run through `sync`, which is happy to be handed
     * the set a record already has — opening the roles dialog and pressing save
     * changing nothing is the common case, not the rare one. Without this guard
     * the trail fills with rows saying a value stayed the same, and the rows that
     * matter become harder to find rather than easier.
     *
     * **Callers pass list values in a stable order** (sorted names, not ids in
     * whatever order the database returned them), because `[a, b]` and `[b, a]`
     * are not equal here and would read as a change.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function recordChange(?Model $subject, AuditEvent $event, array $old, array $new): void
    {
        if ($old === $new) {
            return;
        }

        $this->record($subject, $event, $old, $new);
    }

    /**
     * Whether an audit should be written at all in this context.
     *
     * **This mirrors `Auditable::isAuditingEnabled()` on purpose, and it is not
     * redundant.** The package checks that method before every *model* event, but
     * its `RecordCustomAudit` listener — the one behind {@see AuditCustom} —
     * checks nothing. Without this guard the console rule would hold for ordinary
     * saves and silently not hold for everything in this class, so a seeder that
     * assigned a role would write an audit while the user it created wrote none.
     */
    private function enabled(): bool
    {
        if (! config('audit.enabled', true)) {
            return false;
        }

        return ! App::runningInConsole() || (bool) config('audit.console', false);
    }

    /**
     * Write the audit through the package, so one code path stamps every row.
     *
     * The staging and clearing live on {@see Audited} rather than
     * here, because the properties they set belong to the package's *trait* and
     * a caller holding only the contract cannot see them. {@see Auditable}
     * declares the two operations so this can name them.
     *
     * The `finally` is not tidiness: the staged state lives on the model
     * instance, so an instance left prepared would write the next ordinary save
     * as another custom event.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function recordThroughPackage(Auditable&Model $subject, AuditEvent $event, array $old, array $new): void
    {
        $subject->prepareCustomAudit($event->value, $old, $new);

        try {
            Event::dispatch(new AuditCustom($subject));
        } finally {
            $subject->clearCustomAudit();
        }
    }

    /**
     * Write an audit that names no record.
     *
     * The package cannot be used here — every path through it starts from an
     * `Auditable` model — so the row is built directly, and it has to stamp the
     * same columns the resolvers would. It reads them off the request rather than
     * calling the configured resolver classes, which all require the very model
     * this branch exists because it does not have.
     *
     * `auditable_type` / `auditable_id` stay null, which is what
     * `nullableMorphs()` in the migration is for.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function recordWithoutSubject(AuditEvent $event, array $old, array $new): void
    {
        $actor = Auth::user();
        $request = request();

        AuditLog::query()->create([
            'user_type' => $actor instanceof User ? $actor->getMorphClass() : null,
            'user_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor instanceof User ? $actor->name : null,
            'actor_employee_id' => $actor instanceof User ? $actor->employee_id : null,
            'event' => $event->value,
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => $old,
            'new_values' => $new,
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'tags' => null,
        ]);
    }
}
