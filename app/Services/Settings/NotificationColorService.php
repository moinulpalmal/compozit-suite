<?php

namespace App\Services\Settings;

use App\Models\Settings\NotificationColor;
use App\Models\Settings\TnaTemplateColor;
use Illuminate\Database\Eloquent\Builder;

/**
 * Creates, updates and deletes notification colours, and decides which ones a
 * form may offer.
 *
 * **{@see self::deletionBlocker()} was owed, and this is where it came due.**
 * This class shipped without one deliberately: nothing referenced a notification
 * colour, and a blocker that can only ever return `null` is dead code no test can
 * exercise and that reads as a guard while guarding nothing. The debt was recorded
 * against whichever feature took the first foreign key.
 *
 * That feature is TNA. `tna_template_colors.notification_color_id` holds a colour
 * so a milestone can be drawn in it, so deleting a colour a template paints with is
 * now refused with a `warning` toast, the way `DesignationController::destroy`
 * refuses. It lives here rather than in a policy because a refusal about the
 * *record's* state cannot: `Gate::before` bypasses a policy for a super admin
 * (ARCHITECTURE.md §9.1), and a super admin deleting a referenced colour would
 * break the ladder just as thoroughly. See documentation/settings.md §3.5.
 *
 * The foreign key is `restrictOnDelete`, so the database refuses too — but it
 * refuses with an integrity-constraint exception, which is a stack trace rather
 * than a sentence. The blocker exists to say something a person can act on.
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
     *
     * Call {@see self::deletionBlocker()} first — this does not check.
     */
    public function delete(NotificationColor $notificationColor): void
    {
        $notificationColor->delete();
    }

    /**
     * The reason this colour may not be deleted, if there is one.
     *
     * Deactivating is how a colour somebody uses is retired: it leaves the pickers
     * — `assignableOptions()` keeps it visible to records already holding it — while
     * remaining available to the templates that reference it. Deleting is for
     * colours nothing has ever used.
     *
     * Counting templates rather than rungs is deliberate. A colour appears at most
     * once per template (the unique constraint on `tna_template_colors` sees to it),
     * so the two counts are equal, but "three templates use this" is the sentence
     * that tells someone where to go and fix it.
     */
    public function deletionBlocker(NotificationColor $notificationColor): ?string
    {
        $templates = $this->templateCount($notificationColor);

        if ($templates === 0) {
            return null;
        }

        return trans_choice(
            '{1} One TNA template colours a milestone with this. Deactivate it instead, or change that template to another colour.'
            .'|[2,*] :count TNA templates colour a milestone with this. Deactivate it instead, or change them to another colour.',
            $templates,
            ['count' => $templates],
        );
    }

    /**
     * How many TNA templates paint a milestone with this colour.
     */
    public function templateCount(NotificationColor $notificationColor): int
    {
        return TnaTemplateColor::query()
            ->where('notification_color_id', $notificationColor->id)
            ->distinct()
            ->count('tna_template_id');
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
