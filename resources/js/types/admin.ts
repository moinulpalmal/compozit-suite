import type { ListFilters } from '@/types/ui';

/**
 * Admin module types — RBAC surfaces. Re-exported from `@/types`.
 *
 * The list apparatus's own types — `ListFilters`, `Filterable`, `FilterMatch`,
 * `Paginated`, `StatusOption` — used to live here, and moved to `types/ui.ts`
 * when the apparatus itself moved to `components/shared/` (ARCHITECTURE.md
 * §6.5). They describe a mechanism, not an Admin surface. Nothing outside
 * `types/` imported them by path, so `@/types` consumers saw no change.
 */

export type PermissionOption = {
    id: number;
    name: string;
    resource: string;
    action: string;
};

/** Permissions keyed by their module segment, as the pickers render them. */
export type PermissionGroups = Record<string, PermissionOption[]>;

export type RoleListItem = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
    is_super_admin: boolean;
    is_deletable: boolean;
};

export type RoleDetail = {
    id: number;
    name: string;
    is_super_admin: boolean;
    permissions: string[];
};

export type PermissionListItem = {
    id: number;
    name: string;
    module: string;
    roles: string[];
};

export type PermissionDetail = {
    id: number;
    name: string;
    roles: string[];
};

/** A gender option as `Gender::options()` serialises it. */
export type GenderOption = {
    value: string;
    label: string;
};

export type DesignationListItem = {
    id: number;
    name: string;
    short_form: string | null;
    /** `'A'` active, `'I'` retired from the pickers. Not a soft delete. */
    status: string;
    /** Holders, soft-deleted users included. */
    users_count: number;
    /** False while anybody holds it — the server refuses the delete too. */
    is_deletable: boolean;
};

/**
 * A designation the user form may offer.
 *
 * The list includes an inactive designation when a row on the current page
 * still holds it, so nobody's title is silently blanked on save. `status` is
 * what lets the select label that option.
 */
export type DesignationOption = {
    value: number;
    label: string;
    short_form: string | null;
    status: string;
};

/** A designation as the users-list filter dropdown renders it. */
export type DesignationFilterOption = {
    value: number;
    label: string;
};

export type BuyerListItem = {
    id: number;
    name: string;
    code: string | null;
    /** `'A'` active, `'I'` retired from the pickers. Not a soft delete. */
    status: string;
    /**
     * Users granted this buyer explicitly. **Not** the number of people who can
     * see it: anyone with `all_buyer_access` has no pivot row and sees it anyway.
     */
    users_count: number;
};

/** A buyer as the access picker renders it — `hint` is the code. */
export type BuyerOption = {
    value: number;
    label: string;
    hint: string | null;
};

/** A module segment, as the permission list's filter renders it. */
export type ModuleOption = {
    value: string;
    label: string;
};

export type UserListItem = {
    id: number;
    name: string;
    employee_id: string;
    email: string;
    personal_mobile_no: string | null;
    official_mobile_no: string | null;
    official_extension_no: string | null;
    gender: string;
    designation_id: number | null;
    /** Null on rows created before designations existed. */
    designation: string | null;
    /** `'A'` active, `'I'` disabled — `RecordStatus`, shared with designations. */
    status: string;
    approval_authority: boolean;
    roles: string[];
    inserted_by: string | null;
    last_updated_by: string | null;
    created_at: string | null;
    /** Set only on the historical tab. */
    deleted_at: string | null;
    /** The signed-in user's own row — several actions are refused on it. */
    is_self: boolean;
    is_last_super_admin: boolean;
    /**
     * Present only when the viewer holds `admin.buyer-access.view` — the
     * controller does not load the relation otherwise, and a `0` would be a lie
     * rather than an absence.
     */
    all_buyer_access?: boolean;
    /** Explicit grants. Always 0 when `all_buyer_access` is true — the flag replaces them. */
    buyers_count?: number;
    buyers?: BuyerOption[];
};

export type UserFilters = ListFilters & {
    /**
     * Which record set is listed. Not a column filter — it chooses between the
     * live table and the soft-deleted history, which is why it is `view` and not
     * a cell in the filter row.
     */
    view: 'active' | 'trashed';
};

export type DesignationFilters = ListFilters;

export type BuyerFilters = ListFilters;

export type RoleFilters = ListFilters;

export type PermissionFilters = ListFilters;

/**
 * A recorded change, as `Admin\AuditLogService::describe()` ships it.
 *
 * `old_values` and `new_values` ride with every row, so the diff dialog opens
 * without a request. That is affordable only because the six JSON payload
 * columns are excluded from auditing (ARCHITECTURE.md §9.3); if one is ever
 * audited, this becomes a fetch.
 */
export type AuditLogListItem = {
    id: number;
    /** The raw stored string. Matches `App\Enums\Admin\AuditEvent` for known events. */
    event: string;
    event_label: string;
    /** A morph alias (`purchase-order`), never a class name. Null for authentication events. */
    auditable_type: string | null;
    auditable_id: number | null;
    model_label: string | null;
    /**
     * Stamped at write time, so it survives the account being deleted. Null when
     * there was no authenticated actor.
     */
    actor_name: string | null;
    actor_employee_id: string | null;
    user_id: number | null;
    /** The union of both sides' keys — a delete has no new values, a create no old ones. */
    changed: string[];
    old_values: Record<string, unknown>;
    new_values: Record<string, unknown>;
    ip_address: string | null;
    url: string | null;
    user_agent: string | null;
    tags: string | null;
    created_at: string | null;
};

export type AuditLogFilters = ListFilters;
