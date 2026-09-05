import type { AuditLogListItem } from '@/types';

/**
 * One audit's old and new values, field by field.
 *
 * The union of both sides' keys, so a `deleted` row (old values, no new) and a
 * `created` row (the reverse) both render every field they touched. Values are
 * rendered as JSON rather than coerced to strings: a `null` and the string
 * `"null"` mean different things in a trail, and an audit that could not tell
 * them apart would be worse than none.
 *
 * The `inserted_by` / `last_updated_by` ids arrive already resolved to names —
 * `Admin\AuditLogService::describeValues()` does that server-side, in one query
 * for the whole page.
 */
export default function AuditDiffTable({ audit }: { audit: AuditLogListItem }) {
    const fields = Array.from(
        new Set([
            ...Object.keys(audit.old_values),
            ...Object.keys(audit.new_values),
        ]),
    );

    if (fields.length === 0) {
        return (
            <p className="py-4 text-center text-sm text-base-content/60">
                No field changes were recorded for this event.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-box border border-base-300/70">
            <table className="table table-sm">
                <thead>
                    <tr>
                        <th className="w-1/4">Field</th>
                        <th>Before</th>
                        <th>After</th>
                    </tr>
                </thead>
                <tbody>
                    {fields.map((field) => (
                        <tr key={field}>
                            <td className="font-mono text-xs font-medium">
                                {field}
                            </td>
                            <td className="font-mono text-xs break-all text-base-content/70">
                                <Value
                                    present={field in audit.old_values}
                                    value={audit.old_values[field]}
                                />
                            </td>
                            <td className="font-mono text-xs break-all">
                                <Value
                                    present={field in audit.new_values}
                                    value={audit.new_values[field]}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * One side of one field.
 *
 * "Absent" and "null" are drawn differently on purpose. A field missing from one
 * side means the event had no such side — a created record has no before — while
 * a stored `null` means the column genuinely held nothing.
 */
function Value({ present, value }: { present: boolean; value: unknown }) {
    if (!present) {
        return <span className="text-base-content/40">—</span>;
    }

    if (value === null) {
        return <span className="text-base-content/50 italic">null</span>;
    }

    if (typeof value === 'string') {
        return value === '' ? (
            <span className="text-base-content/50 italic">empty</span>
        ) : (
            <>{value}</>
        );
    }

    return <>{JSON.stringify(value)}</>;
}
