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
