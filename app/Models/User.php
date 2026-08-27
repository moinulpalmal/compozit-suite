<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Admin\Gender;
use App\Enums\Theme;
use App\Models\Admin\Designation;
use App\Observers\ActorObserver;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
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
 * @property bool $approved
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
    'approved',
    'approval_authority',
    'email',
    'password',
    'theme',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

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
            'approved' => 'boolean',
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
     * The fields the Admin user list may be searched by.
     *
     * Searching one named field rather than OR-ing across all of them is what
     * keeps the query indexable: an `OR` over six columns forces MySQL into an
     * unreliable index merge or a scan, whereas one column is a range scan on
     * that column's index every time. See ARCHITECTURE.md §6.3.
     *
     * @var list<string>
     */
    public const array SEARCHABLE = [
        'name',
        'employee_id',
        'email',
        'personal_mobile_no',
        'official_mobile_no',
        'official_extension_no',
    ];

    /**
     * The columns the Admin user list may be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['name', 'employee_id', 'email', 'created_at'];

    /**
     * Filter users by a prefix match on one searchable field.
     *
     * **Prefix, not contains.** `LIKE 'term%'` uses an index; `LIKE '%term%'`
     * cannot, ever. So "158" finds employee 15868 but "868" does not — that is
     * the contract, not a bug.
     *
     * @param  Builder<User>  $query
     */
    public function scopeSearch(Builder $query, ?string $field, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '' || ! in_array($field, self::SEARCHABLE, true)) {
            return;
        }

        // Otherwise a term of "%" becomes a wildcard and scans the whole table.
        $escaped = addcslashes($term, '%_\\');

        $query->where($field, 'like', "{$escaped}%");
    }

    /**
     * Order the list by an allow-listed column.
     *
     * The allow-list is load-bearing: passing request input straight to
     * `orderBy()` is a SQL injection. An unknown column falls back to the
     * default rather than reaching the database.
     *
     * @param  Builder<User>  $query
     */
    public function scopeSortBy(Builder $query, ?string $column, ?string $direction): void
    {
        $column = in_array($column, self::SORTABLE, true) ? $column : 'name';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction);
    }
}
