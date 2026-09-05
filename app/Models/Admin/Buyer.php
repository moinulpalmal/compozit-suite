<?php

namespace App\Models\Admin;

use App\Concerns\Audited;
use App\Concerns\HasStatus;
use App\Concerns\Listable;
use App\Contracts\Auditable;
use App\Enums\FilterType;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Observers\ActorObserver;
use Database\Factories\Admin\BuyerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A customer the factory produces for.
 *
 * The unit every buyer-owned record is scoped by — see ARCHITECTURE.md §9.2.
 * Unlike a designation, which grants nothing, being granted a buyer decides
 * which rows a user can see at all, so the grants are administered behind
 * `admin.buyer-access.*` rather than as an ordinary profile field.
 *
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property RecordStatus $status
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $insertedBy
 * @property-read User|null $lastUpdatedBy
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Department> $departments
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable(['name', 'code', 'status'])]
class Buyer extends Model implements Auditable
{
    /** @use HasFactory<BuyerFactory> */
    use Audited, HasFactory, HasStatus, Listable;

    /**
     * The columns the buyer list's filter row exposes.
     *
     * `name` is {@see FilterType::Contains} — buyers are typed from memory and
     * finding "sport" inside "Zara Sportswear" is worth the scan on a table this
     * small. `code` is {@see FilterType::Prefix}, which is both how anybody types
     * a code and what keeps its unique index seekable. Never infer either from
     * the column type; both are `varchar` (ARCHITECTURE.md §6.3).
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'name' => FilterType::Contains,
        'code' => FilterType::Prefix,
        'status' => FilterType::Equals,
    ];

    /**
     * The columns the buyer list may be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'code', 'status', 'created_at'];

    /**
     * The users granted access to this buyer.
     *
     * Users holding `all_buyer_access` are **absent** from this relation — the
     * flag is their grant and the pivot stays empty for them, so this is not a
     * complete answer to "who can see this buyer". See ARCHITECTURE.md §9.2.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * This buyer's own merchandise departments.
     *
     * `Department` is `BuyerScoped`, so reading this relation as a non-super-admin
     * returns only what the actor may see. `BuyerService::deletionBlocker()`
     * deliberately escapes that with `withoutBuyerScope()` — a department the
     * actor cannot see still blocks the buyer's deletion.
     *
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
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
