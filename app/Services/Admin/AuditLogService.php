<?php

namespace App\Services\Admin;

use App\Concerns\Audited;
use App\Models\Admin\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Reads the audit trail for the Admin browser.
 *
 * **Read-only, and it has no sibling that writes.** The trail is written by the
 * package through {@see Audited} and by {@see AuditRecorder}; a
 * service that could edit it would defeat the point of having one.
 */
class AuditLogService
{
    /**
     * The columns whose values are user ids and read better as names.
     *
     * `ActorObserver` stamps both on every write it can (ARCHITECTURE.md §9.3), so
     * `last_updated_by` appears in the diff of almost every update. They are not
     * excluded from auditing — the trail should say what the row now holds — but a
     * bare `7` in a diff tells a reader nothing.
     *
     * @var list<string>
     */
    private const array USER_REFERENCE_KEYS = ['inserted_by', 'last_updated_by', 'user_id'];

    /**
     * The model types the browser offers, from the morph map.
     *
     * **Derived, never hand-maintained.** The reference implementation kept a
     * short-name→class array by hand and it drifted to 18 of its 32 audited
     * models, so more than a third of the trail could not be filtered to or opened
     * in the history view. Reading the map means the list is right by
     * construction, and a model added to `AppServiceProvider::MORPH_MAP` appears
     * here with nothing else to remember.
     *
     * @return list<array{value: string, label: string}>
     */
    public function modelTypes(): array
    {
        $aliases = array_keys(Relation::morphMap());

        sort($aliases);

        return array_map(
            fn (string $alias): array => ['value' => $alias, 'label' => $this->labelForType($alias)],
            $aliases,
        );
    }

    /**
     * Shape one page of audits for the list.
     *
     * @param  Collection<int, AuditLog>  $audits
     * @return list<array<string, mixed>>
     */
    public function describePage(Collection $audits): array
    {
        $names = $this->actorNamesFor($audits);

        return array_values(
            $audits->map(fn (AuditLog $audit): array => $this->describe($audit, $names))->all(),
        );
    }

    /**
     * Every audit for one record, newest first.
     *
     * Feeds the record-history dialog. Ordered by id as well as by timestamp
     * because several audits of one record routinely share a second — an import
     * writes an order and retires its predecessor inside one transaction — and
     * `created_at` alone would order them arbitrarily.
     *
     * @return array<int, array<string, mixed>>
     */
    public function historyFor(string $type, int $id): array
    {
        /** @var Collection<int, AuditLog> $audits */
        $audits = AuditLog::query()
            ->where('auditable_type', $type)
            ->where('auditable_id', $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->describePage($audits);
    }

    /**
     * Shape one audit row.
     *
     * Public because the list shapes its page through the paginator's
     * `->through()` — the house pattern — which maps one row at a time. The names
     * are resolved once beforehand by {@see self::actorNamesFor()} and passed in,
     * so a hundred-row page still costs one query rather than two hundred.
     *
     * @param  array<int, string>  $names  user id => display name
     * @return array<string, mixed>
     */
    public function describe(AuditLog $audit, array $names): array
    {
        $old = $this->describeValues($audit->old_values ?? [], $names);
        $new = $this->describeValues($audit->new_values ?? [], $names);

        return [
            'id' => $audit->id,
            'event' => $audit->event,
            'event_label' => $audit->eventCase()?->label() ?? $audit->event,
            'auditable_type' => $audit->auditable_type,
            'auditable_id' => $audit->auditable_id,
            'model_label' => $audit->auditable_type === null
                ? null
                : $this->labelForType($audit->auditable_type),
            'actor_name' => $audit->actor_name,
            'actor_employee_id' => $audit->actor_employee_id,
            'user_id' => $audit->user_id,
            /*
             * The union of both sides' keys — a deleted row has old values and no
             * new ones, and a created row the reverse, so neither alone names every
             * field the change touched.
             */
            'changed' => array_values(array_unique([...array_keys($old), ...array_keys($new)])),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $audit->ip_address,
            'url' => $audit->url,
            'user_agent' => $audit->user_agent,
            'tags' => $audit->tags,
            'created_at' => $audit->created_at?->toIso8601String(),
        ];
    }

    /**
     * Render a diff's values, naming the ones that are user ids.
     *
     * A value that is not a known user id is left exactly as stored — including a
     * `null`, which in this trail means "was not set" and must not be confused
     * with a user who could not be found.
     *
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function describeValues(array $values, array $names): array
    {
        foreach (self::USER_REFERENCE_KEYS as $key) {
            $id = $values[$key] ?? null;

            if (is_numeric($id) && isset($names[(int) $id])) {
                $values[$key] = $names[(int) $id];
            }
        }

        return $values;
    }

    /**
     * The display names of every user referenced by a page of audits.
     *
     * **One query for the whole page**, which is the point. The implementation
     * this was ported from called `User::find()` per key per row — two hundred
     * queries to draw one column of a hundred-row page.
     *
     * `withTrashed()` for the reason `actor_name` is denormalised at all: a
     * deleted account is exactly the one whose history somebody is reading.
     *
     * Takes `Support\Collection` rather than the Eloquent one so a paginator's
     * `getCollection()` can be handed straight to it.
     *
     * @param  Collection<int, AuditLog>  $audits
     * @return array<int, string>
     */
    public function actorNamesFor(Collection $audits): array
    {
        $ids = [];

        foreach ($audits as $audit) {
            foreach ([$audit->old_values ?? [], $audit->new_values ?? []] as $values) {
                foreach (self::USER_REFERENCE_KEYS as $key) {
                    if (is_numeric($values[$key] ?? null)) {
                        $ids[] = (int) $values[$key];
                    }
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        return User::withTrashed()
            ->whereKey(array_unique($ids))
            ->get(['id', 'name', 'employee_id'])
            ->mapWithKeys(fn (User $user): array => [
                $user->id => $user->name.' ('.$user->employee_id.')',
            ])
            ->all();
    }

    /**
     * A morph alias as a person reads it — `purchase-order` becomes "Purchase order".
     */
    private function labelForType(string $alias): string
    {
        return Str::ucfirst(str_replace('-', ' ', $alias));
    }
}
