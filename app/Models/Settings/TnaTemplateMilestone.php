<?php

namespace App\Models\Settings;

use App\Concerns\Audited;
use App\Enums\Merchandising\TnaMilestone;
use Database\Factories\Settings\TnaTemplateMilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * One milestone of a template, and how many days after the BQS date it falls.
 *
 * Only milestones {@see TnaMilestone::offsetFromBqs()} is true for may appear here;
 * `Shipment` comes from the purchase order and a row claiming to schedule it is
 * refused by the write requests.
 *
 * There is no `ActorObserver` and no `status`. A child row is not independently
 * curated — it is part of the template above it, is written and rewritten as a set,
 * and dies with it on `cascadeOnDelete`. Who changed the schedule is recorded on
 * {@see TnaTemplate}, which is the thing a person edits.
 *
 * @property int $id
 * @property int $tna_template_id
 * @property TnaMilestone $milestone
 * @property int $offset_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TnaTemplate $template
 */
#[Fillable(['milestone', 'offset_days'])]
class TnaTemplateMilestone extends Model implements Auditable
{
    /** @use HasFactory<TnaTemplateMilestoneFactory> */
    use Audited, HasFactory;

    /**
     * Cast the milestone to its enum and the offset to a number.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'milestone' => TnaMilestone::class,
            'offset_days' => 'integer',
        ];
    }

    /**
     * The template this milestone belongs to.
     *
     * @return BelongsTo<TnaTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TnaTemplate::class, 'tna_template_id');
    }
}
