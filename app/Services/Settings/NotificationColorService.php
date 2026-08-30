<?php

namespace App\Services\Settings;

use App\Models\Settings\NotificationColor;
use Illuminate\Database\Eloquent\Builder;

/**
 * Creates, updates and deletes notification colours, and decides which ones a
 * form may offer.
 *
 * **There is no `deletionBlocker()` here yet, and that is deliberate.**
 * `DesignationService` and `BuyerService` both have one because something
 * references those rows; nothing references a notification colour today. A
 * blocker that can only ever return `null` is dead code that no test can
 * exercise and that reads as a guard while guarding nothing.
 *
 * It becomes owed the moment `notifications.notification_color_id` exists: at
 * that point deleting a colour a notification holds must be refused with a
 * `warning` toast, the way `DesignationController::destroy` refuses, and this
 * is the class it belongs in — a refusal about the *record's* state rather than
 * the actor's power cannot live in a policy, because `Gate::before` bypasses a
 * policy for a super admin (ARCHITECTURE.md §9.1). See documentation/settings.md §3.5.
 */
class NotificationColorService
{
    /**
     * Create a notification colour.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): NotificationColor
    {
        return NotificationColor::create($attributes);
    }

    /**
     * Update a notification colour.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(NotificationColor $notificationColor, array $attributes): void
    {
        $notificationColor->update($attributes);
    }

    /**
     * Delete a notification colour.
     */
    public function delete(NotificationColor $notificationColor): void
    {
        $notificationColor->delete();
    }

    /**
     * The colours a form may offer.
     *
     * Active ones, **plus any id in `$keep` even if it has been retired** — the
     * exception `DesignationService::assignableOptions()` documents: a record
     * already holding a deactivated colour must still see it in its picker, or
     * saving blanks the field or fails validation on a value nobody touched.
     *
     * **Deliberately unpaginated.** A list and its picker are different queries
     * (ARCHITECTURE.md §8.6): paginating the screen must never truncate the
     * dropdown that offers the same records elsewhere. There is a test pinning
     * this, as there is for the designation and permission pickers.
     *
     * @param  list<int>  $keep
     * @return list<array{value: int, label: string, color_code: string, status: string}>
     */
    public function assignableOptions(array $keep = []): array
    {
        $keep = array_values(array_unique(array_filter($keep)));

        return NotificationColor::query()
            ->where(fn (Builder $query) => $query
                ->active()
                ->when($keep !== [], fn (Builder $inner) => $inner->orWhereIn('id', $keep)))
            ->orderBy('name')
            ->get()
            ->map(fn (NotificationColor $color): array => [
                'value' => $color->id,
                'label' => $color->name,
                'color_code' => $color->color_code,
                'status' => $color->status->value,
            ])
            ->all();
    }
}
