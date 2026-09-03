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

/**
 * What the server requires of a new password.
 *
 * Assembled from `config/auth.php` by `HandleInertiaRequests` and shared with
 * every page. It is the *only* source for what the checklist under a password
 * field displays — never restate a rule in a component, which is exactly how
 * the old `PasswordStrength` came to advertise a minimum the validator had
 * stopped enforcing.
 */
export type PasswordPolicy = {
    minLength: number;
    mixedCase: boolean;
    letters: boolean;
    numbers: boolean;
    symbols: boolean;
    /**
     * Whether the password is checked against Have I Been Pwned on save. Not
     * verifiable in the browser, so it is shown as a note rather than a
     * tickable requirement.
     */
    uncompromised: boolean;
    /**
     * The `passwordrules` attribute value Safari and iOS Keychain read when
     * generating a password. Machine-readable; not for display.
     */
    hint: string;
};
