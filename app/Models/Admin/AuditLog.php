<?php

namespace App\Models\Admin;

use App\Concerns\Listable;
use App\Enums\Admin\AuditEvent;
use App\Enums\FilterType;
use App\Models\User;
use Database\Factories\Admin\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Models\Audit;

/**
 * One recorded change — ARCHITECTURE.md §9.3.
 *
 * Extends the package's `Audit` rather than replacing it, so the diffing, the
 * `json` casts and the `$guarded = []` the driver relies on all come for free.
 * `config/audit.php` points `implementation` here and the driver's table at
 * `audit_logs`, which is how the package's names are kept out of the schema.
 *
 * **Nothing writes this model directly except the package and
 * {@see \App\Services\Admin\AuditRecorder}.** There is no create, update or
 * delete path, and there is deliberately no `admin.audit-logs.{create,update,
 * delete}` permission for one to hide behind: a trail an administrator can edit
 * answers nothing.
 *
 * @property int $id
 * @property string|null $user_type
 * @property int|null $user_id
 * @property string|null $actor_name
 * @property string|null $actor_employee_id
 * @property string $event
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $actor
 */
class AuditLog extends Audit
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, Listable;

    /**
     * The columns the audit list's filter row exposes.
     *
     * `actor_name` is {@see FilterType::Contains} and is the only column here that
     * costs a scan. It earns it: the question this screen is opened to answer is
     * almost always "what did this person do", and people search by a fragment of
     * a name. It is a denormalised column rather than a join precisely so that
     * search does not become a per-row subquery.
     *
     * `ip_address` is {@see FilterType::Prefix} for the reason §6.3 gives for
     * identifiers — a subnet is a prefix, and mid-string matching an address finds
     * nothing anybody meant.
     *
     * `auditable_type` holds a morph alias, so its cell is a dropdown built from
     * `Relation::morphMap()` and matches by equality.
     *
     * @var array<string, FilterType>
     */
    public const array FILTERABLE = [
        'actor_name' => FilterType::Contains,
        'event' => FilterType::Equals,
        'auditable_type' => FilterType::Equals,
        'ip_address' => FilterType::Prefix,
    ];

    /**
     * The columns the audit list may be sorted by.
     *
     * @var list<string>
     */
    public const array SORTABLE = ['created_at', 'actor_name', 'event', 'auditable_type'];

    /**
     * Newest first.
     *
     * `SORTABLE[0]` would give the same column, but stating it here is what makes
     * the intent survive somebody reordering that list. The *direction* is the
     * other half and cannot be expressed on the model — see
     * {@see \App\Http\Requests\Admin\AuditLogIndexRequest::filterValues()}.
     */
    public static function defaultSort(): string
    {
        return 'created_at';
    }

    /**
     * The event as a known case, or null if the column holds something else.
     *
     * Not a cast: the column is written by the package as a raw string, and a
     * backed-enum cast would throw on any value the enum has not been taught —
     * turning an unrecognised historical event into a 500 on the list screen
     * rather than a row that renders its own value.
     */
    public function eventCase(): ?AuditEvent
    {
        return AuditEvent::tryFrom($this->event);
    }

    /**
     * The acting user, where the account still exists.
     *
     * **Do not render the name through this relation** — use `actor_name`, which
     * is stamped at write time and survives the account's deletion. This exists
     * for the rare case that wants the live record: a link to the user's profile,
     * say. It is `withTrashed()` for the same reason the column exists at all.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
