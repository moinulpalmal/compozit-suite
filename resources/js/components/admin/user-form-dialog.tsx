import { Form } from '@inertiajs/react';
import { Check, LoaderCircle, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCallback, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import FormDialogFooter from '@/components/shared/form-dialog-footer';
import PasswordPolicyChecklist from '@/components/shared/password-policy-checklist';
import { Checkbox } from '@/components/ui/checkbox';
import Combobox from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AvailabilityState } from '@/hooks/use-availability';
import { useAvailability } from '@/hooks/use-availability';
import { useFormDialog } from '@/hooks/use-form-dialog';
import { options as designationOptionsRoute } from '@/routes/admin/designations';
import type {
    DesignationOption,
    GenderOption,
    StatusOption,
    UserListItem,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/** `/^[A-Za-z0-9-]{3,10}$/` — kept in step with `EmployeeValidationRules`. */
const EMPLOYEE_ID_PATTERN = /^[A-Za-z0-9-]{3,10}$/;

/** `/^01[3-9][0-9]{8}$/` — Bangladeshi mobile numbers. */
const MOBILE_PATTERN = /^01[3-9][0-9]{8}$/;

/**
 * Create/edit a user in a modal — the largest instance of the form-modal standard
 * in ARCHITECTURE.md §8.10, and the one that most needs its focus-on-error rule:
 * a rejected field here can sit several rows above the fold.
 *
 * Client-side checks here are for feedback only — `UserStoreRequest` and
 * `UserUpdateRequest` are what enforce the rules, and their errors render
 * through the same `InputError` slots.
 */
export default function UserFormDialog({
    submit,
    genders,
    designations,
    statuses,
    roles,
    user,
    title,
    description,
    children,
}: {
    submit: RouteFormDefinition<'post'>;
    genders: GenderOption[];
    /** `RecordStatus::options()` — active/inactive. */
    statuses: StatusOption[];
    /**
     * Active designations, plus any retired one a row on this page still
     * holds — see `DesignationService::assignableOptions()`.
     */
    designations: DesignationOption[];
    /** Assignable role names — only offered when creating. */
    roles?: string[];
    user?: UserListItem;
    title: string;
    description: string;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const close = useCallback(() => setOpen(false), []);
    const { formKey, formProps, setIntent } = useFormDialog(close);
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

    const designationSearchUrl = designationOptionsRoute.url();

    // A deactivated title is still offered when this user already holds it, so
    // it has to say so rather than looking like any other choice.
    const designationOptions = useMemo(
        () =>
            designations.map((designation) => ({
                value: designation.value,
                label:
                    designation.status === 'I'
                        ? `${designation.label} (deactivated)`
                        : designation.label,
                hint: designation.short_form ?? undefined,
            })),
        [designations],
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>

            <DialogContent className="max-w-2xl">
                <DialogTitle>{title}</DialogTitle>
                <DialogDescription>{description}</DialogDescription>

                <Form
                    key={formKey}
                    {...submit}
                    {...formProps}
                    options={{ preserveScroll: true }}
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
                                        aria-invalid={Boolean(errors.name)}
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
                                            employeeIdState === 'taken' ||
                                            Boolean(errors.employee_id)
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
                                        aria-invalid={
                                            emailState === 'taken' ||
                                            Boolean(errors.email)
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Gender"
                                    htmlFor="gender"
                                    error={errors.gender}
                                >
                                    <Combobox
                                        id="gender"
                                        name="gender"
                                        defaultValue={user?.gender ?? 'M'}
                                        options={genders}
                                        required
                                        aria-invalid={Boolean(errors.gender)}
                                    />
                                </Field>

                                <Field
                                    label="Designation"
                                    htmlFor="designation_id"
                                    error={errors.designation_id}
                                >
                                    {/* No default guess — the admin has to
                                        choose, and an existing user with no
                                        designation must not silently keep
                                        whichever title sorts first.

                                        The one async consumer in the app: the
                                        designation list is paginated, so this
                                        picker searches the server rather than
                                        being shipped whole. ARCHITECTURE.md
                                        §8.5. The rendered `designations` still
                                        seed it, which is what keeps a retired
                                        title the user already holds visible —
                                        the endpoint returns active ones only. */}
                                    <Combobox
                                        id="designation_id"
                                        name="designation_id"
                                        defaultValue={
                                            user?.designation_id ?? null
                                        }
                                        options={designationOptions}
                                        searchUrl={designationSearchUrl}
                                        placeholder="Choose a designation"
                                        required
                                        aria-invalid={Boolean(
                                            errors.designation_id,
                                        )}
                                        data-test="designation-select"
                                    />
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
                                        aria-invalid={Boolean(
                                            errors.personal_mobile_no,
                                        )}
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
                                        aria-invalid={Boolean(
                                            errors.official_mobile_no,
                                        )}
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
                                        aria-invalid={Boolean(
                                            errors.official_extension_no,
                                        )}
                                    />
                                </Field>

                                {isCreate && (
                                    <Field
                                        label="Password"
                                        htmlFor="password"
                                        error={errors.password}
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
                                            aria-invalid={Boolean(
                                                errors.password,
                                            )}
                                            aria-describedby="password-policy"
                                        />

                                        <PasswordPolicyChecklist
                                            id="password-policy"
                                            password={password}
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
                                            aria-invalid={Boolean(
                                                errors.password_confirmation,
                                            )}
                                        />
                                    </Field>
                                )}
                            </div>

                            <fieldset className="grid gap-2 rounded-box border border-base-300/70 p-4">
                                <legend className="px-1 text-sm font-medium">
                                    Status
                                </legend>

                                {/* `status` was a boolean `approved` checkbox
                                    until RecordStatus made A/I the house
                                    vocabulary. Two options, so the combobox
                                    renders as a plain listbox. */}
                                <div className="grid max-w-xs gap-1.5">
                                    <Label htmlFor="status">
                                        Account status
                                    </Label>
                                    <Combobox
                                        id="status"
                                        name="status"
                                        defaultValue={user?.status ?? 'A'}
                                        options={statuses}
                                        required
                                        aria-invalid={Boolean(errors.status)}
                                        data-test="user-status"
                                    />
                                    <p className="text-xs text-base-content/60">
                                        Inactive accounts cannot sign in.
                                    </p>
                                    <InputError message={errors.status} />
                                </div>

                                {/* `approval_authority` stays a boolean: it is
                                    a power flag, not an active flag. Unchecked
                                    checkboxes submit nothing, so a hidden 0
                                    backs it. */}
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

                                <InputError
                                    message={errors.approval_authority}
                                />
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

                            <FormDialogFooter
                                processing={processing}
                                addAnother={isCreate}
                                onIntent={setIntent}
                                saveTestId="save-user"
                            />
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

/*
 * `PasswordStrength` used to live here — a Weak/Fair/Strong label scored
 * against a hardcoded copy of the password policy, including a `length >= 12`
 * tier. It was still advertising that minimum after the policy dropped to 8,
 * because nothing connected the two. `components/shared/password-policy-checklist.tsx`
 * replaces it and reads the rules from the server instead.
 */

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
