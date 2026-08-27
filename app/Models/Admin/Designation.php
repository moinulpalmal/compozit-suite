<?php

namespace App\Models\Admin;

use App\Enums\Admin\DesignationStatus;
use App\Models\User;
use App\Observers\ActorObserver;
use Database\Factories\Admin\DesignationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
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
 * @property DesignationStatus $status
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
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DesignationStatus::class,
        ];
    }

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

    /**
     * Limit the query to designations that may still be assigned.
     *
     * @param  Builder<Designation>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', DesignationStatus::Active);
    }

    /**
     * Determine whether this designation may still be assigned to a user.
     */
    public function isActive(): bool
    {
        return $this->status === DesignationStatus::Active;
    }
}
