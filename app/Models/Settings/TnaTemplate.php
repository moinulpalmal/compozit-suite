<?php

namespace App\Models\Settings;

use App\Concerns\Audited;
use App\Concerns\HasStatus;
use App\Concerns\Listable;
use App\Enums\FilterType;
use App\Enums\Merchandising\TnaMilestone;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Observers\ActorObserver;
use Database\Factories\Settings\TnaTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * A reusable schedule, matched to a purchase order by how long its programme runs.
 *
 * Settings-owned master data (ARCHITECTURE.md §9.4): Merchandising reads it to draw
 * the TNA page and never writes it, exactly as it reads {@see NotificationColor}.
 *
 * A template answers two questions about one lead-time band — when each milestone
 * falls (`milestones`, days after the BQS date) and how urgently a date reads as it
 * approaches (`colors`). Both are child rows rather than columns; the migrations say
 * why.
 *
 * @property int $id
 * @property string $name
 * @property int $lead_time_from
 * @property int $lead_time_to
 * @property RecordStatus $status
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TnaTemplateMilestone> $milestones
 * @property-read Collection<int, TnaTemplateColor> $colors
 * @property-read User|null $insertedBy
 * @property-read User|null $lastUpdatedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable(['name', 'lead_time_from', 'lead_time_to', 'status'])]
class TnaTemplate extends Model implements Auditable
{
    /** @use HasFactory<TnaTemplateFactory> */
    use Audited, HasFactory, HasStatus, Listable;

    /**
     * The columns the list's filter row exposes.
     *
     * `name` is {@see FilterType::Contains} — the table is small and finding a word
     * mid-string is what someone typing here wants.
     *
     * **The lead-time bounds are deliberately absent.** Every cell in this row is an
     * equality or a string match, and an exact-match cell on a band's endpoint
     * answers a question nobody asks ("which template starts on exactly day 241?").
     * The question people do ask — "which template covers 263 days?" — is a
     * containment search across two columns, which is a different control and a
     * different wire format. The same reasoning keeps `retention_days` off
     * {@see NotificationColor::FILTERABLE}.
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'name' => FilterType::Contains,
        'status' => FilterType::Equals,
    ];

    /**
     * The columns the list may be sorted by.
     *
     * `lead_time_from` is sortable even though it is not filterable: reading the
     * register in band order is how you check it for gaps, which is the one thing
     * this screen is for.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'lead_time_from', 'lead_time_to', 'status', 'created_at'];

    /**
     * Order the register by band rather than by name.
     *
     * Gaps and the shape of the ladder are visible in band order and invisible in
     * alphabetical order.
     */
    public static function defaultSort(): string
    {
        return 'lead_time_from';
    }

    /**
     * Cast the bounds so they reach the front end as numbers.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_time_from' => 'integer',
            'lead_time_to' => 'integer',
        ];
    }

    /**
     * The template covering a given lead time, if one is active.
     *
     * **Active only.** Deactivating a band is how it is retired without deleting the
     * history of why it existed, so an inactive row must not match — and because it
     * cannot match, it is also exempt from the overlap rule the write requests apply.
     *
     * Both bounds are inclusive. If two active bands somehow overlap — they cannot be
     * saved that way, but a direct database edit could — the lower one wins, which is
     * at least deterministic.
     *
     * @param  Builder<static>  $query
     */
    public function scopeCovering(Builder $query, int $leadTimeDays): void
    {
        $query->active()
            ->where('lead_time_from', '<=', $leadTimeDays)
            ->where('lead_time_to', '>=', $leadTimeDays)
            ->orderBy('lead_time_from');
    }

    /**
     * When each planned milestone falls, in days after the BQS date.
     *
     * @return HasMany<TnaTemplateMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(TnaTemplateMilestone::class);
    }

    /**
     * The urgency ladder, ordered the way it must be read.
     *
     * Ascending by bound with the `null` catch-all last, so the first band whose
     * bound covers a date is the one that applies. Ordering here rather than at each
     * call site is what stops a caller getting the ladder subtly wrong — see
     * {@see TnaTemplateColor} for why the null must sort last rather than first.
     *
     * @return HasMany<TnaTemplateColor, $this>
     */
    public function colors(): HasMany
    {
        return $this->hasMany(TnaTemplateColor::class)
            ->orderByRaw('max_days_remaining is null')
            ->orderBy('max_days_remaining');
    }

    /**
     * The offset in days for one milestone, or null if this template omits it.
     */
    public function offsetFor(TnaMilestone $milestone): ?int
    {
        return $this->milestones
            ->firstWhere('milestone', $milestone)
            ?->offset_days;
    }

    /**
     * The user who created this record, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function insertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inserted_by');
    }

    /**
     * The user who last changed this record, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
