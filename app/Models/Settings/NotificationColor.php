<?php

namespace App\Models\Settings;

use App\Concerns\HasStatus;
use App\Concerns\Listable;
use App\Concerns\NotificationColorValidationRules;
use App\Enums\FilterType;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Observers\ActorObserver;
use Database\Factories\Settings\NotificationColorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A colour a notification can be raised in, and how long it is kept.
 *
 * Settings-owned product/process reference data (ARCHITECTURE.md §9.4): every
 * other module reads it by foreign key and none of them write it.
 *
 * `color_code` is stored as uppercase `#RRGGBB`. The normalisation lives in the
 * write requests, not here — see {@see NotificationColorValidationRules}
 * for why a mutator would defeat the unique rule.
 *
 * @property int $id
 * @property string $name
 * @property string $color_code
 * @property int $retention_days
 * @property RecordStatus $status
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $insertedBy
 * @property-read User|null $lastUpdatedBy
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable(['name', 'color_code', 'retention_days', 'status'])]
class NotificationColor extends Model
{
    /** @use HasFactory<NotificationColorFactory> */
    use HasFactory, HasStatus, Listable;

    /**
     * The columns the list's filter row exposes.
     *
     * `name` is {@see FilterType::Contains} — the table is small, so the scan is
     * trivial and finding a word mid-string is what someone typing here wants.
     *
     * `color_code` is {@see FilterType::Prefix}: a hex is read and typed
     * left-to-right from the `#`, and a prefix stays seekable on the unique
     * index where a leading wildcard would not (ARCHITECTURE.md §6.3). Note the
     * consequence — the term has to start with `#` to match anything.
     *
     * **`retention_days` is deliberately absent.** Every cell in this row is an
     * equality or a string match, and an exact-match cell on a duration answers
     * a question nobody asks ("which colours are kept for exactly 31 days?").
     * A range filter is a different control and a different wire format; if one
     * is ever wanted it is a decision to record, not a key to add here.
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'name' => FilterType::Contains,
        'color_code' => FilterType::Prefix,
        'status' => FilterType::Equals,
    ];

    /**
     * The columns the list may be sorted by.
     *
     * `retention_days` is sortable even though it is not filterable: ordering by
     * a duration is a question people do ask, and it costs nothing.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'color_code', 'retention_days', 'status', 'created_at'];

    /**
     * Cast the duration so it reaches the front end as a number.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retention_days' => 'integer',
        ];
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
