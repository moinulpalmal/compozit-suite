/**
 * Admin module types — RBAC surfaces. Re-exported from `@/types`.
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

/** A status option as `RecordStatus::options()` serialises it. */
export type StatusOption = {
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

/**
 * How one column's filter cell matches, mirroring `App\Enums\FilterType`.
 *
 * `contains` finds mid-string and cannot use an index; `prefix` is indexable and
 * is why "868" does not find employee 15868. The page shows the difference in
 * each cell's placeholder rather than leaving people to guess.
 */
export type FilterMatch = 'contains' | 'prefix' | 'equals' | 'scope';

/** A model's `FILTERABLE` map, as the controller ships it. */
export type Filterable = Record<string, FilterMatch>;

/**
 * The query state every Admin list screen carries, as `ListRequest::filters()`
 * serialises it. Surface-specific keys extend this.
 */
export type ListFilters = {
    /** Allow-listed column name; anything else is rejected server-side. */
    sort: string;
    direction: 'asc' | 'desc';
    /** One of `perPageOptions`; anything else is rejected server-side. */
    per_page: number;
    /**
     * The filter row's values, keyed by column. Every filterable column is
     * present, blank when unfiltered, so the row renders from this alone.
     */
    filter: Record<string, string>;
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

/** One page of a Laravel paginator, as Inertia serialises it. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
    prev_page_url: string | null;
    next_page_url: string | null;
};
