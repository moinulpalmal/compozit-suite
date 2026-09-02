<?php

namespace App\Services\Settings;

use App\Enums\RecordStatus;
use App\Models\Settings\TnaTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Creates, updates and deletes TNA templates together with their child rows.
 *
 * A template is only meaningful as a whole — a band, the offsets inside it and the
 * urgency ladder that colours them — so all three are written in one transaction and
 * the children are **replaced rather than merged** on update. Merging would need a
 * stable identity for a rung that the form does not have and the user does not think
 * in: they edit a ladder, not four rows.
 *
 * There is no `deletionBlocker()` here. Nothing references a template — the TNA page
 * matches one at read time and stores no link to it — so deleting one changes which
 * schedule an order is drawn with and destroys nothing. That is a visible effect, not
 * a corruption. Contrast {@see NotificationColorService::deletionBlocker()}, which
 * exists precisely because this feature's colour rungs *do* hold a foreign key.
 */
class TnaTemplateService
{
    /**
     * Create a template and its children.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): TnaTemplate
    {
        return DB::transaction(function () use ($attributes): TnaTemplate {
            $template = TnaTemplate::create($this->ownAttributes($attributes));

            $this->syncChildren($template, $attributes);

            return $template;
        });
    }

    /**
     * Update a template, replacing its children.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(TnaTemplate $template, array $attributes): void
    {
        DB::transaction(function () use ($template, $attributes): void {
            $template->update($this->ownAttributes($attributes));

            $template->milestones()->delete();
            $template->colors()->delete();

            $this->syncChildren($template, $attributes);
        });
    }

    /**
     * Delete a template. Its children go with it on `cascadeOnDelete`.
     */
    public function delete(TnaTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Whether an active band overlapping these days already exists.
     *
     * **Active rows only**, and that is the whole subtlety: deactivating a band is how
     * it is retired without losing the record of it, so a retired band must be free to
     * overlap its replacement. It cannot match an order either — {@see TnaTemplate::scopeCovering()}
     * agrees — so permitting the overlap creates no ambiguity.
     *
     * Two ranges overlap when each starts on or before the other ends. Expressed that
     * way rather than as four cases because the four-case version is where this kind
     * of check usually goes wrong.
     */
    public function overlaps(int $from, int $to, ?int $ignoreId = null): bool
    {
        return TnaTemplate::query()
            ->active()
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('lead_time_from', '<=', $to)
            ->where('lead_time_to', '>=', $from)
            ->exists();
    }

    /**
     * The template's own columns, without the child collections.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function ownAttributes(array $attributes): array
    {
        return [
            'name' => $attributes['name'],
            'lead_time_from' => $attributes['lead_time_from'],
            'lead_time_to' => $attributes['lead_time_to'],
            'status' => $attributes['status'] ?? RecordStatus::Active->value,
        ];
    }

    /**
     * Write the milestone offsets and the colour ladder.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function syncChildren(TnaTemplate $template, array $attributes): void
    {
        /** @var list<array{milestone: string, offset_days: int}> $milestones */
        $milestones = $attributes['milestones'] ?? [];

        /** @var list<array{notification_color_id: int, max_days_remaining: int|null}> $colors */
        $colors = $attributes['colors'] ?? [];

        if ($milestones !== []) {
            $template->milestones()->createMany($milestones);
        }

        if ($colors !== []) {
            $template->colors()->createMany($colors);
        }

        $template->load(['milestones', 'colors.color']);
    }
}
