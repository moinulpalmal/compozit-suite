import { Form } from '@inertiajs/react';
import { Check, LoaderCircle, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AvailabilityState } from '@/hooks/use-availability';
import { useAvailability } from '@/hooks/use-availability';
import type { GenderOption, UserListItem } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/** `/^[A-Za-z0-9-]{3,10}$/` — kept in step with `EmployeeValidationRules`. */
const EMPLOYEE_ID_PATTERN = /^[A-Za-z0-9-]{3,10}$/;

/** `/^01[3-9][0-9]{8}$/` — Bangladeshi mobile numbers. */
const MOBILE_PATTERN = /^01[3-9][0-9]{8}$/;

/**
 * Create/edit a user in a modal.
 *
 * Client-side checks here are for feedback only — `UserStoreRequest` and
 * `UserUpdateRequest` are what enforce the rules, and their errors render
 * through the same `InputError` slots.
 */
export default function UserFormDialog({
    submit,
    genders,
    roles,
    user,
    title,
    description,
    submitLabel,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    genders: GenderOption[];
    /** Assignable role names — only offered when creating. */
    roles?: string[];
    user?: UserListItem;
    title: string;
    description: string;
    submitLabel: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const isCreate = user === undefined;

    const [employeeId, setEmployeeId] = useState(user?.employee_id ?? '');
    const [email, setEmail] = useState(user?.email ?? '');
    const [password, setPassword] = useState('');
    const [touched, setTouched] = useState<Record<string, boolean>>({});

    const employeeIdState = useAvailability(
        'employee_id',
        employeeId,
        user?.id,
    );
    const emailState = useAvailability('email', email, user?.id);

    const markTouched = (field: string) =>
        setTouched((current) => ({ ...current, [field]: true }));

    const employeeIdFormat =
        touched.employee_id &&
        employeeId !== '' &&
        !EMPLOYEE_ID_PATTERN.test(employeeId)
            ? 'Must be 3–10 letters, digits or hyphens.'
            : undefined;

    const mobileFormat = (field: string, value: string) =>
        touched[field] && value !== '' && !MOBILE_PATTERN.test(value)
            ? 'Must be 11 digits starting 013–019.'
            : undefined;

    const [personalMobile, setPersonalMobile] = useState(
        user?.personal_mobile_no ?? '',
    );
    const [officialMobile, setOfficialMobile] = useState(
        user?.official_mobile_no ?? '',
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent className="max-w-2xl">
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form
                    {...submit}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="mt-4 space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Full name"
                                    htmlFor="name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={user?.name}
                                        required
                                        autoFocus
                                        autoComplete="off"
                                    />
                                </Field>

                                <Field
                                    label="Employee ID"
                                    htmlFor="employee_id"
                                    error={
                                        errors.employee_id ?? employeeIdFormat
                                    }
                                    hint={
                                        <AvailabilityHint
                                            state={employeeIdState}
                                        />
                                    }
                                >
                                    <Input
                                        id="employee_id"
                                        name="employee_id"
                                        value={employeeId}
                                        onChange={(event) =>
                                            setEmployeeId(event.target.value)
                                        }
                                        onBlur={() =>
                                            markTouched('employee_id')
                                        }
                                        required
                                        maxLength={10}
                                        autoComplete="off"
                                        placeholder="15868"
                                        aria-invalid={
                                            employeeIdState === 'taken'
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Email address"
                                    htmlFor="email"
                                    error={errors.email}
                                    hint={
                                        <AvailabilityHint state={emailState} />
                                    }
                                >
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        value={email}
                                        onChange={(event) =>
                                            setEmail(event.target.value)
                                        }
                                        required
                                        autoComplete="off"
                                        aria-invalid={emailState === 'taken'}
                                    />
                                </Field>

                                <Field
                                    label="Gender"
                                    htmlFor="gender"
                                    error={errors.gender}
                                >
                                    <select
                                        id="gender"
                                        name="gender"
                                        defaultValue={user?.gender ?? 'M'}
                                        className="select w-full"
                                    >
                                        {genders.map((gender) => (
                                            <option
                                                key={gender.value}
                                                value={gender.value}
                                            >
                                                {gender.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <Field
                                    label="Personal mobile"
                                    htmlFor="personal_mobile_no"
                                    error={
                                        errors.personal_mobile_no ??
                                        mobileFormat(
                                            'personal_mobile_no',
                                            personalMobile,
                                        )
                                    }
                                >
                                    <Input
                                        id="personal_mobile_no"
                                        name="personal_mobile_no"
                                        value={personalMobile}
                                        onChange={(event) =>
                                            setPersonalMobile(
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            markTouched('personal_mobile_no')
                                        }
                                        inputMode="numeric"
                                        maxLength={11}
                                        placeholder="01712345678"
                                    />
                                </Field>

                                <Field
                                    label="Official mobile"
                                    htmlFor="official_mobile_no"
                                    error={
                                        errors.official_mobile_no ??
                                        mobileFormat(
                                            'official_mobile_no',
                                            officialMobile,
                                        )
                                    }
                                >
                                    <Input
                                        id="official_mobile_no"
                                        name="official_mobile_no"
                                        value={officialMobile}
                                        onChange={(event) =>
                                            setOfficialMobile(
                                                event.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            markTouched('official_mobile_no')
                                        }
                                        inputMode="numeric"
                                        maxLength={11}
                                        placeholder="01812345678"
                                    />
                                </Field>

                                <Field
                                    label="Extension"
                                    htmlFor="official_extension_no"
                                    error={errors.official_extension_no}
                                >
                                    <Input
                                        id="official_extension_no"
                                        name="official_extension_no"
                                        defaultValue={
                                            user?.official_extension_no ?? ''
                                        }
                                        inputMode="numeric"
                                        maxLength={4}
                                        placeholder="204"
                                    />
                                </Field>

                                {isCreate && (
                                    <Field
                                        label="Password"
                                        htmlFor="password"
                                        error={errors.password}
                                        hint={
                                            <PasswordStrength
                                                password={password}
                                            />
                                        }
                                    >
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            value={password}
                                            onChange={(event) =>
                                                setPassword(event.target.value)
                                            }
                                            required
                                            autoComplete="new-password"
                                        />
                                    </Field>
                                )}

                                {isCreate && (
                                    <Field
                                        label="Confirm password"
                                        htmlFor="password_confirmation"
                                        error={errors.password_confirmation}
                                    >
                                        <PasswordInput
                                            id="password_confirmation"
                                            name="password_confirmation"
                                            required
                                            autoComplete="new-password"
                                        />
                                    </Field>
                                )}
                            </div>

                            <fieldset className="grid gap-2 rounded-box border border-base-300/70 p-4">
                                <legend className="px-1 text-sm font-medium">
                                    Status
                                </legend>

                                {/* Unchecked checkboxes submit nothing, so a hidden 0 backs each one. */}
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="approved"
                                        value="0"
                                    />
                                    <Checkbox
                                        name="approved"
                                        value="1"
                                        defaultChecked={user?.approved ?? true}
                                    />
                                    Active — may sign in
                                </label>

                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="hidden"
                                        name="approval_authority"
                                        value="0"
                                    />
                                    <Checkbox
                                        name="approval_authority"
                                        value="1"
                                        defaultChecked={
                                            user?.approval_authority ?? false
                                        }
                                    />
                                    Approval authority
                                </label>

                                <InputError message={errors.approved} />
                            </fieldset>

                            {isCreate && roles !== undefined && (
                                <fieldset className="grid gap-2 rounded-box border border-base-300/70 p-4">
                                    <legend className="px-1 text-sm font-medium">
                                        Roles
                                    </legend>

                                    {roles.length === 0 && (
                                        <p className="text-sm text-base-content/60">
                                            No roles exist yet.
                                        </p>
                                    )}

                                    {roles.map((role) => (
                                        <label
                                            key={role}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                name="roles[]"
                                                value={role}
                                            />
                                            <span className="font-mono text-xs">
                                                {role}
                                            </span>
                                        </label>
                                    ))}

                                    <InputError message={errors.roles} />
                                </fieldset>
                            )}

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing}
                                    data-test="save-user"
                                >
                                    {submitLabel}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

/** Label + control + hint + error, so the grid above stays readable. */
function Field({
    label,
    htmlFor,
    error,
    hint,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    hint?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="grid content-start gap-1.5">
            <div className="flex items-baseline justify-between gap-2">
                <Label htmlFor={htmlFor}>{label}</Label>
                {hint}
            </div>

            {children}

            <InputError message={error} />
        </div>
    );
}

/** Live result of the server-side uniqueness check. */
function AvailabilityHint({ state }: { state: AvailabilityState }) {
    if (state === 'idle') {
        return null;
    }

    if (state === 'checking') {
        return (
            <span className="flex items-center gap-1 text-xs text-base-content/60">
                <LoaderCircle className="size-3 animate-spin" /> Checking
            </span>
        );
    }

    if (state === 'available') {
        return (
            <span className="flex items-center gap-1 text-xs text-success">
                <Check className="size-3" /> Available
            </span>
        );
    }

    return (
        <span className="flex items-center gap-1 text-xs text-error">
            <X className="size-3" /> Already taken
        </span>
    );
}

/**
 * A rough strength read-out. `Password::default()` on the server is the rule
 * that actually decides — this only tells the admin how they are doing.
 */
export function PasswordStrength({ password }: { password: string }) {
    if (password === '') {
        return null;
    }

    const score = [
        password.length >= 8,
        password.length >= 12,
        /[a-z]/.test(password) && /[A-Z]/.test(password),
        /[0-9]/.test(password),
        /[^A-Za-z0-9]/.test(password),
    ].filter(Boolean).length;

    const [label, tone] =
        score <= 2
            ? ['Weak', 'text-error']
            : score === 3 || score === 4
              ? ['Fair', 'text-warning']
              : ['Strong', 'text-success'];

    return <span className={`text-xs ${tone}`}>{label}</span>;
}
