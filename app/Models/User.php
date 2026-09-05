<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\Audited;
use App\Concerns\HasStatus;
use App\Concerns\Listable;
use App\Enums\Admin\Gender;
use App\Enums\FilterType;
use App\Enums\RecordStatus;
use App\Enums\Theme;
use App\Models\Admin\Buyer;
use App\Models\Admin\Designation;
use App\Models\Admin\Role;
use App\Observers\ActorObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $employee_id
 * @property string|null $personal_mobile_no
 * @property string|null $official_mobile_no
 * @property string|null $official_extension_no
 * @property Gender $gender
 * @property int|null $designation_id
 * @property RecordStatus $status
 * @property bool $approval_authority
 * @property bool $all_buyer_access
 * @property int|null $inserted_by
 * @property int|null $last_updated_by
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Theme|null $theme
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $insertedBy
 * @property-read User|null $lastUpdatedBy
 * @property-read Designation|null $designation
 * @property-read Collection<int, Buyer> $buyers
 */
#[ObservedBy(ActorObserver::class)]
#[Fillable([
    'name',
    'employee_id',
    'personal_mobile_no',
    'official_mobile_no',
    'official_extension_no',
    'gender',
    'designation_id',
    'status',
    'approval_authority',
    'email',
    'password',
    'theme',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<UserFactory> */
    use Audited, HasFactory, HasRoles, HasStatus, Listable, Notifiable, SoftDeletes;

    /**
     * Attributes the audit trail must never record.
     *
     * **This list is not a duplicate of `#[Hidden]` above — it is load-bearing on
     * its own.** `config('audit.strict')` is `false`, and the package honours a
     * model's hidden attributes *only* in strict mode. Without this property every
     * one of these lands in `audit_logs.new_values` in clear, on the very first
     * password change, readable from an admin screen.
     *
     * It mirrors `#[Hidden]` exactly and must keep mirroring it: a fifth secret
     * added there is not protected here until it is added here too. The two cannot
     * be collapsed, because a model's `$auditExclude` *replaces* rather than merges
     * with `config('audit.exclude')`, and `#[Hidden]` governs serialisation rather
     * than auditing.
     *
     * @var array<int, string>
     */
    protected $auditExclude = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Memoised buyer ids — see {@see self::accessibleBuyerIds()}.
     *
     * A real declared property, not an attribute: written through `$this->…` on
     * a model, an undeclared name would become a *database column* on the next
     * save.
     *
     * @var list<int>|null
     */
    protected ?array $accessibleBuyerIds = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'theme' => Theme::class,
            'gender' => Gender::class,
            'approval_authority' => 'boolean',
            'all_buyer_access' => 'boolean',
        ];
    }

    /**
     * The buyers this user has been granted access to.
     *
     * **Empty for a user holding `all_buyer_access`** — the flag is the grant and
     * the pivot is cleared when it goes on, so this relation is not the question
     * to ask about visibility. {@see self::seesAllBuyers()} is. See
     * ARCHITECTURE.md §9.2.
     *
     * @return BelongsToMany<Buyer, $this>
     */
    public function buyers(): BelongsToMany
    {
        return $this->belongsToMany(Buyer::class);
    }

    /**
     * Whether every buyer is visible to this user, present and future.
     *
     * The super-admin arm is not redundant with `Gate::before`: that bypass
     * grants *abilities*, and buyer scope is row filtering, so without this a
     * newly promoted super admin with no grants would see an empty application.
     * This is the ARCHITECTURE.md §9.1 exception that permits naming the role.
     */
    public function seesAllBuyers(): bool
    {
        return $this->all_buyer_access || $this->hasRole(Role::SUPER_ADMIN);
    }

    /**
     * The buyer ids this user may see, memoised for the life of the instance.
     *
     * `BuyerScope` runs on every query against every buyer-owned model, so a
     * fresh `buyer_user` round trip per query is not affordable. Callers that
     * change a user's access re-read them from a fresh instance — the service
     * does exactly that.
     *
     * @return list<int>
     */
    public function accessibleBuyerIds(): array
    {
        return $this->accessibleBuyerIds ??= $this->buyers()
            ->pluck('buyers.id')
            ->map(intval(...))
            ->all();
    }

    /**
     * The job title this user holds.
     *
     * Descriptive only — never consult it in an authorization check. Nullable
     * because the column predates the requirement: rows created before
     * designations existed have none, while the form requests make it
     * mandatory for every user created or edited since.
     *
     * @return BelongsTo<Designation, $this>
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * The user who created this record, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function insertedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'inserted_by');
    }

    /**
     * The user who last changed this record, if there was an authenticated actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'last_updated_by');
    }

    /**
     * The columns the Admin user list's filter row exposes.
     *
     * Each cell filters its own column and the cells are `AND`-ed, which is what
     * keeps this indexable — the leading predicate still uses an index and the
     * rest are cheap residual filters. It is `OR`-ing one term across every
     * column that MySQL cannot serve, and that is not what this does.
     * See ARCHITECTURE.md §6.3.
     *
     * The identifiers stay {@see FilterType::Prefix}: they are the columns with
     * indexes built for exactly that lookup, and prefix is how anybody actually
     * types an employee ID or a phone number. Names and emails are
     * {@see FilterType::Contains}, where finding mid-string is worth the scan.
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'name' => FilterType::Contains,
        'employee_id' => FilterType::Prefix,
        'email' => FilterType::Contains,
        'personal_mobile_no' => FilterType::Prefix,
        'official_mobile_no' => FilterType::Prefix,
        'official_extension_no' => FilterType::Prefix,
        'gender' => FilterType::Equals,
        'designation_id' => FilterType::Equals,
        'status' => FilterType::Equals,
    ];

    /**
     * The columns the Admin user list may be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'employee_id', 'email', 'created_at'];
}
