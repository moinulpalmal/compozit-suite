<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasStatus;
use App\Concerns\Listable;
use App\Enums\Admin\Gender;
use App\Enums\FilterType;
use App\Enums\RecordStatus;
use App\Enums\Theme;
use App\Models\Admin\Designation;
use App\Observers\ActorObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
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
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasStatus, Listable, Notifiable, SoftDeletes;

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
        ];
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
