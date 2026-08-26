export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /**
     * The signed-in user's effective permission names, or `['*']` for a super
     * admin. Shared by `HandleInertiaRequests`; read it with `useCan()`.
     */
    permissions: string[];
};
