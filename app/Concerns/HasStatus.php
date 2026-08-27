<?php

namespace App\Concerns;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * An active/inactive `status` column, cast and queryable.
 *
 * This is what makes {@see RecordStatus} *usable* rather than merely shared: a
 * model gets the cast and both scopes from one `use`, instead of every table
 * re-declaring the same four things and drifting.
 *
 * Requires a `status` column — `string(1)`, defaulting to `RecordStatus::Active`.
 * Add `'status'` to the model's `#[Fillable]` if a form should write it.
 *
 * Note that `status` is **not** `deleted_at`. Deactivating retires a record
 * from the pickers while leaving it in place; deleting is a separate verb with
 * its own guard. See documentation/admin.md §8.2.
 */
trait HasStatus
{
    /**
     * Cast `status` without the model having to declare it.
     *
     * Eloquent calls `initialize{Trait}` for every used trait from the model
     * constructor, which is how `SoftDeletes` adds `deleted_at` to `$dates`.
     */
    public function initializeHasStatus(): void
    {
        $this->mergeCasts(['status' => RecordStatus::class]);
    }

    /**
     * Limit the query to records in active use.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', RecordStatus::Active);
    }

    /**
     * Limit the query to records that have been retired.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInactive(Builder $query): void
    {
        $query->where('status', RecordStatus::Inactive);
    }

    /**
     * Determine whether this record is in active use.
     */
    public function isActive(): bool
    {
        return $this->status === RecordStatus::Active;
    }
}
