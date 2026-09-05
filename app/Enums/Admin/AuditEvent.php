<?php

namespace App\Enums\Admin;

/**
 * The vocabulary of the audit trail's `event` column.
 *
 * **The first four are a contract with `config/audit.php`.** The package writes
 * those raw strings itself, so a case renamed here without renaming it there
 * produces a filter option that matches no row — and nothing would fail, the
 * dropdown would simply always come back empty.
 *
 * The rest are this application's own, written through
 * `Admin\AuditRecorder` for the things Eloquent events cannot see: pivot grants,
 * the importers' bulk updates, and authentication. Each names *what happened*
 * rather than which table moved, because the row already carries the table.
 *
 * Kebab-case values, matching the permission and route vocabulary (§6.2), so the
 * column reads the same way the rest of the application's identifiers do.
 */
enum AuditEvent: string
{
    /*
    |--------------------------------------------------------------------------
    | Written by the package
    |--------------------------------------------------------------------------
    */

    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case Restored = 'restored';

    /*
    |--------------------------------------------------------------------------
    | Written by Admin\AuditRecorder
    |--------------------------------------------------------------------------
    */

    /** Roles granted to or revoked from a user. */
    case RolesChanged = 'roles-changed';

    /** Permissions attached to or detached from a role, or a role from a permission. */
    case PermissionsChanged = 'permissions-changed';

    /** A user's buyer access changed — the pivot, or the all-access flag. */
    case BuyerAccessChanged = 'buyer-access-changed';

    /**
     * A revision was retired or re-parented by an importer.
     *
     * Covers the `is_current` and `root_id` mass updates, which are query-builder
     * writes and therefore invisible to model events.
     */
    case RevisionRetired = 'revision-retired';

    /** A BQS row was linked to, or unlinked from, purchase-order lines. */
    case BqsLinkChanged = 'bqs-link-changed';

    /** A document was imported — one entry standing for the whole batch of rows. */
    case Imported = 'imported';

    case LoggedIn = 'logged-in';
    case LoggedOut = 'logged-out';

    /** A rejected sign-in attempt. Records the employee ID and never the password. */
    case LoginFailed = 'login-failed';

    /**
     * The events as the filter cell's dropdown renders them.
     *
     * The same shape `RecordStatus::options()` returns, so `ColumnFilterRow`
     * consumes it unchanged.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $event): array => ['value' => $event->value, 'label' => $event->label()],
            self::cases(),
        );
    }

    /**
     * A human label for the event.
     */
    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Updated => 'Updated',
            self::Deleted => 'Deleted',
            self::Restored => 'Restored',
            self::RolesChanged => 'Roles changed',
            self::PermissionsChanged => 'Permissions changed',
            self::BuyerAccessChanged => 'Buyer access changed',
            self::RevisionRetired => 'Revision retired',
            self::BqsLinkChanged => 'BQS link changed',
            self::Imported => 'Imported',
            self::LoggedIn => 'Signed in',
            self::LoggedOut => 'Signed out',
            self::LoginFailed => 'Sign-in failed',
        };
    }
}
