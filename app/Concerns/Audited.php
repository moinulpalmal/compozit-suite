<?php

namespace App\Concerns;

use App\Models\User;
use App\Observers\ActorObserver;
use App\Services\Admin\AuditRecorder;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Auditable;

/**
 * Records every change to a model in `audit_logs` — ARCHITECTURE.md §9.3.
 *
 * A model opts in with two lines, which is the whole registration:
 *
 * ```php
 * use App\Concerns\Audited;
 * use App\Contracts\Auditable;
 *
 * class Designation extends Model implements Auditable
 * {
 *     use Audited;
 * }
 * ```
 *
 * **The interface is this application's own** ({@see \App\Contracts\Auditable}),
 * which extends the package's and adds the two custom-event operations below. A
 * model therefore names one interface and no package namespace appears in it.
 *
 * **The trait is named `Audited`, not `Auditable`,** so it cannot collide with
 * that interface in the `use` list of every single model. The adjectival form
 * also matches {@see BuyerScoped} and {@see Listable}.
 *
 * The nesting works because Laravel's `Model::bootTraits()` walks
 * `class_uses_recursive()`, so the package's own `bootAuditable()` still fires
 * through this wrapper and attaches its observer. There is no
 * `bootAudited()` and none is needed.
 *
 * Two hooks are overridden here rather than per model, so that what the trail
 * records cannot drift between one model and the next — the same argument
 * {@see ActorObserver} makes for being one shared class.
 */
trait Audited
{
    use Auditable;

    /**
     * Tag the audit with the buyer that owns the record.
     *
     * The audit browser filters on this. It is read straight off the attribute
     * rather than declared per model, so a table that gains a `buyer_id` later is
     * tagged with no further registration — exactly how `BuyerScoped` treats the
     * same column.
     *
     * **This is a filter, not an access control.** The trail is readable only by a
     * super admin (§9.1), who sees every buyer anyway; if that access is ever
     * widened, this tag is what a scope would have to be built on, and it does not
     * become one by itself.
     *
     * A record belonging to no buyer is untagged rather than tagged with a
     * sentinel, so the `LIKE` the filter performs can never match it by accident.
     *
     * @return list<string>
     */
    public function generateTags(): array
    {
        $buyerId = $this->getAttribute('buyer_id');

        return $buyerId === null ? [] : ['buyer:'.$buyerId];
    }

    /**
     * Stamp who the actor was, by name, at the moment of the write.
     *
     * The package stores only `user_id` and leaves the name to a join at read
     * time. That is wrong here: `users` is soft-deleted (§9.6) and the default
     * user provider applies the global scope, so a deleted account's whole history
     * would resolve to null and render as "System" — the record would still exist
     * and would still be unreadable. Copying the name at write time is what an
     * audit trail is supposed to do anyway: it records the state as it was, not as
     * the `users` table later became.
     *
     * `transformAudit()` is the package's last hook before the row is written, so
     * this applies to package-written and {@see AuditRecorder}
     * custom events alike.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function transformAudit(array $data): array
    {
        $actor = Auth::user();

        return [
            ...$data,
            'actor_name' => $actor instanceof User ? $actor->name : null,
            'actor_employee_id' => $actor instanceof User ? $actor->employee_id : null,
        ];
    }

    /**
     * Stage an event the package's Eloquent observers cannot produce.
     *
     * The four properties come from the package trait `use`d above, which is why
     * this lives here rather than in {@see AuditRecorder}: inside the trait they
     * are visible, and to a caller holding only the contract they are not.
     *
     * `auditEvent` is assigned rather than passed through `setAuditEvent()`,
     * which nulls any name outside `config('audit.events')` — every event this
     * path writes is outside it by definition, and the null then surfaces as an
     * `AuditingException` from `toAudit()` rather than as anything readable.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function prepareCustomAudit(string $event, array $old, array $new): void
    {
        $this->auditEvent = $event;
        $this->isCustomEvent = true;
        $this->auditCustomOld = $old;
        $this->auditCustomNew = $new;
    }

    /**
     * Discard staged custom-event state.
     *
     * **`isCustomEvent` is the one that must be reset**, and the reason this
     * method exists: `resolveAttributeGetter()` consults it before anything else,
     * so an instance left staged would write its next ordinary save as another
     * custom event with the previous event's values.
     *
     * `auditEvent` is deliberately *not* cleared. The package's observer calls
     * `setAuditEvent()` before every dispatch, so a stale value here is
     * overwritten before it can be read — and the property is declared
     * `@var string` by the package even though its own `setAuditEvent()` assigns
     * null to it, so writing null would be claiming a type the package does not
     * admit to.
     */
    public function clearCustomAudit(): void
    {
        $this->isCustomEvent = false;
        $this->auditCustomOld = null;
        $this->auditCustomNew = null;
    }
}
