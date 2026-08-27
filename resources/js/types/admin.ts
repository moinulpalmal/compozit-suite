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

export type UserListItem = {
    id: number;
    name: string;
    employee_id: string;
    email: string;
    personal_mobile_no: string | null;
    official_mobile_no: string | null;
    official_extension_no: string | null;
    gender: string;
    approved: boolean;
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
};

export type UserFilters = {
    filter: 'active' | 'trashed';
    /** Allow-listed column name; anything else is rejected server-side. */
    sort: string;
    direction: 'asc' | 'desc';
    /** Which single column `search` is matched against. */
    search_field: string;
    /** Matched as a **prefix** — "158" finds 15868, "868" does not. */
    search: string;
    gender: string;
    status: '' | 'active' | 'inactive';
};

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
