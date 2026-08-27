<?php

namespace App\Models\Admin;

use App\Concerns\HasStatus;
use App\Concerns\Listable;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Observers\ActorObserver;
use Database\Factories\Admin\DesignationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A job title a user can hold.
 *
 * **Descriptive only.** A designation never grants anything — permissions come
 * from roles (ARCHITECTURE.md §9.1) and approval power from
 * `users.approval_authority`. Never branch on a designation in an
 * authorization check.
 *
 * @property int $id
 * @property string $name
 * @property string|null $short_form
 * @property RecordStatus $status
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $insertedBy
 * @property-read User|null $lastUpdatedBy
 * @property-read Collection<int, User> $users
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable(['name', 'short_form', 'status'])]
class Designation extends Model
{
    /** @use HasFactory<DesignationFactory> */
    use HasFactory, HasStatus, Listable;

    /**
     * The fields the designation list may be searched by.
     *
     * One named field rather than an `OR` across both, for the reason in
     * ARCHITECTURE.md §6.3 — `name` is covered by its unique index.
     *
     * @var list<string>
     */
    public const array SEARCHABLE = ['name', 'short_form'];

    /**
     * The columns the designation list may be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'short_form', 'status', 'created_at'];

    /**
     * The users holding this designation.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
