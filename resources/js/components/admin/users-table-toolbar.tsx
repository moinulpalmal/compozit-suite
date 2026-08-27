import { Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type {
    DesignationFilterOption,
    GenderOption,
    UserFilters,
} from '@/types';

/** Human labels for the searchable column names the server allow-lists. */
const SEARCH_LABELS: Record<string, string> = {
    name: 'Name',
    employee_id: 'Employee ID',
    email: 'Email',
    personal_mobile_no: 'Personal mobile',
    official_mobile_no: 'Official mobile',
    official_extension_no: 'Extension',
};

/**
 * Filter bar for the user list: gender, status, and a field-scoped search.
 *
 * Search is scoped to **one** column on purpose. Matching a term against every
 * column with `OR` is the shape MySQL cannot index — see ARCHITECTURE.md §6.3.
 * Matching is by prefix, which the placeholder says out loud so nobody files a
 * bug about "868" not finding employee 15868.
 */
export default function UsersTableToolbar({
    filters,
    searchable,
    genders,
    designations,
    onChange,
}: {
    filters: UserFilters;
    searchable: string[];
    genders: GenderOption[];
    /** Every designation, retired ones included. */
    designations: DesignationFilterOption[];
    onChange: (next: Partial<UserFilters>) => void;
}) {
    const [term, setTerm] = useState(filters.search);
    const [field, setField] = useState(filters.search_field);

    return (
        <div className="flex flex-wrap items-end gap-3">
            <label className="grid gap-1">
                <span className="text-xs text-base-content/60">Gender</span>
                <select
                    className="select select-sm"
                    value={filters.gender}
                    onChange={(event) =>
                        onChange({ gender: event.target.value })
                    }
                    aria-label="Filter by gender"
                >
                    <option value="">All genders</option>
                    {genders.map((gender) => (
                        <option key={gender.value} value={gender.value}>
                            {gender.label}
                        </option>
                    ))}
                </select>
            </label>

            <label className="grid gap-1">
                <span className="text-xs text-base-content/60">
                    Designation
                </span>
                <select
                    className="select select-sm"
                    value={filters.designation}
                    onChange={(event) =>
                        onChange({ designation: event.target.value })
                    }
                    aria-label="Filter by designation"
                    data-test="designation-filter"
                >
                    {/* Deactivated designations are listed too: a retired title
                        still has holders and they have to be findable. */}
                    <option value="">All designations</option>
                    {designations.map((designation) => (
                        <option
                            key={designation.value}
                            value={String(designation.value)}
                        >
                            {designation.label}
                        </option>
                    ))}
                </select>
            </label>

            <label className="grid gap-1">
                <span className="text-xs text-base-content/60">Status</span>
                <select
                    className="select select-sm"
                    value={filters.status}
                    onChange={(event) =>
                        onChange({
                            status: event.target.value as UserFilters['status'],
                        })
                    }
                    aria-label="Filter by status"
                >
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </label>

            <form
                className="flex items-end gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    onChange({ search: term, search_field: field });
                }}
            >
                <label className="grid gap-1">
                    <span className="text-xs text-base-content/60">
                        Search in
                    </span>
                    <select
                        className="select select-sm"
                        value={field}
                        onChange={(event) => setField(event.target.value)}
                        aria-label="Field to search"
                    >
                        {searchable.map((name) => (
                            <option key={name} value={name}>
                                {SEARCH_LABELS[name] ?? name}
                            </option>
                        ))}
                    </select>
                </label>

                <Input
                    type="search"
                    className="w-56 input-sm"
                    value={term}
                    onChange={(event) => setTerm(event.target.value)}
                    placeholder={`Starts with… (${SEARCH_LABELS[field] ?? field})`}
                    aria-label="Search term"
                />

                <Button type="submit" size="sm" variant="secondary">
                    <Search /> Search
                </Button>

                {(filters.search !== '' ||
                    filters.gender !== '' ||
                    filters.designation !== '' ||
                    filters.status !== '') && (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            setTerm('');
                            onChange({
                                search: '',
                                gender: '',
                                designation: '',
                                status: '',
                            });
                        }}
                    >
                        Clear
                    </Button>
                )}
            </form>
        </div>
    );
}
