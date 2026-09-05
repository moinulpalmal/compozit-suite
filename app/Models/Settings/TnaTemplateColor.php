<?php

namespace App\Models\Settings;

use App\Concerns\Audited;
use App\Contracts\Auditable;
use Database\Factories\Settings\TnaTemplateColorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One rung of a template's urgency ladder.
 *
 * `max_days_remaining` is the **inclusive upper bound** of the band, in days between
 * today and the planned date. Negative means the date has passed; `null` is the
 * catch-all for everything further out than the last numbered rung.
 *
 * The ordering that makes the ladder readable lives on
 * {@see TnaTemplate::colors()} — nulls last, then ascending — because a caller that
 * ordered it any other way would silently pick the wrong colour rather than fail.
 *
 * As with {@see TnaTemplateMilestone}: no observer and no status, because a rung is
 * part of the template above it rather than a record anyone curates on its own.
 *
 * @property int $id
 * @property int $tna_template_id
 * @property int $notification_color_id
 * @property int|null $max_days_remaining
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TnaTemplate $template
 * @property-read NotificationColor $color
 */
#[Fillable(['notification_color_id', 'max_days_remaining'])]
class TnaTemplateColor extends Model implements Auditable
{
    /** @use HasFactory<TnaTemplateColorFactory> */
    use Audited, HasFactory;

    /**
     * Cast the bound so it reaches the front end as a number or null.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_days_remaining' => 'integer',
        ];
    }

    /**
     * Whether this rung covers a date the given number of days away.
     *
     * The catch-all covers everything, which is what makes "first match wins" safe
     * once the ladder is ordered with it last.
     */
    public function covers(int $daysRemaining): bool
    {
        return $this->max_days_remaining === null
            || $daysRemaining <= $this->max_days_remaining;
    }

    /**
     * The template this rung belongs to.
     *
     * @return BelongsTo<TnaTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TnaTemplate::class, 'tna_template_id');
    }

    /**
     * The Settings colour this rung paints with.
     *
     * `restrictOnDelete` on the foreign key, because this is the first reference
     * into that register and a deleted colour would blank a cell that a reader
     * would take to mean "nothing to worry about".
     *
     * @return BelongsTo<NotificationColor, $this>
     */
    public function color(): BelongsTo
    {
        return $this->belongsTo(NotificationColor::class, 'notification_color_id');
    }
}
