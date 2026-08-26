import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import type { PermissionGroups } from '@/types';

/**
 * Checkbox grid over the permission catalogue, grouped by module.
 *
 * Submits as `permissions[]`, so it drops straight into an Inertia `<Form>`.
 */
export default function PermissionPicker({
    groups,
    defaultSelected = [],
    disabled = false,
}: {
    groups: PermissionGroups;
    defaultSelected?: string[];
    disabled?: boolean;
}) {
    const [selected, setSelected] = useState<string[]>(defaultSelected);

    const toggle = (name: string) => {
        setSelected((current) =>
            current.includes(name)
                ? current.filter((item) => item !== name)
                : [...current, name],
        );
    };

    const toggleModule = (module: string, checked: boolean) => {
        const names = groups[module].map((permission) => permission.name);

        setSelected((current) =>
            checked
                ? [...new Set([...current, ...names])]
                : current.filter((item) => !names.includes(item)),
        );
    };

    const modules = Object.keys(groups);

    if (modules.length === 0) {
        return (
            <p className="text-sm text-base-content/60">
                No permissions exist yet. Create one first, or run the RBAC
                seeder.
            </p>
        );
    }

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {modules.map((module) => {
                const permissions = groups[module];
                const checkedCount = permissions.filter((permission) =>
                    selected.includes(permission.name),
                ).length;

                return (
                    <fieldset
                        key={module}
                        className="rounded-box border border-base-300/70 p-4"
                    >
                        <legend className="px-1">
                            <label className="flex items-center gap-2 text-sm font-medium">
                                <Checkbox
                                    checked={
                                        checkedCount === permissions.length
                                    }
                                    ref={(node) => {
                                        if (node) {
                                            node.indeterminate =
                                                checkedCount > 0 &&
                                                checkedCount <
                                                    permissions.length;
                                        }
                                    }}
                                    disabled={disabled}
                                    onChange={(event) =>
                                        toggleModule(
                                            module,
                                            event.target.checked,
                                        )
                                    }
                                />
                                {module}
                            </label>
                        </legend>

                        <div className="mt-2 grid gap-1.5">
                            {permissions.map((permission) => (
                                <label
                                    key={permission.id}
                                    className="flex items-center gap-2 text-sm"
                                    title={permission.name}
                                >
                                    <Checkbox
                                        name="permissions[]"
                                        value={permission.name}
                                        checked={selected.includes(
                                            permission.name,
                                        )}
                                        disabled={disabled}
                                        onChange={() => toggle(permission.name)}
                                    />
                                    <span className="font-mono text-xs">
                                        {permission.resource}.
                                        {permission.action}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </fieldset>
                );
            })}
        </div>
    );
}
