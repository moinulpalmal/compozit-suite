<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Auditable;

/**
 * Records every change to a model in `audit_logs` — ARCHITECTURE.md §9.3.
 *
 * A model opts in with two lines, which is the whole registration:
 *
 * ```php
 * class Designation extends Model implements \OwenIt\Auditing\Contracts\Auditable
 * {
 *     use Audited;
 * }
 * ```
 *
 * **It is named `Audited`, not `Auditable`.** The package's *contract* is
 * `OwenIt\Auditing\Contracts\Auditable` and every model has to import it by that
 * name; a trait sharing it would collide in the `use` list of every single model.
 * The adjectival form also matches {@see BuyerScoped} and {@see Listable}.
 *
 * The nesting works because Laravel's `Model::bootTraits()` walks
 * `class_uses_recursive()`, so the package's own `bootAuditable()` still fires
 * through this wrapper and attaches its observer. There is no
 * `bootAudited()` and none is needed.
 *
 * Two hooks are overridden here rather than per model, so that what the trail
 * records cannot drift between one model and the next — the same argument
 * {@see \App\Observers\ActorObserver} makes for being one shared class.
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
     * this applies to package-written and {@see \App\Services\Admin\AuditRecorder}
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
}
