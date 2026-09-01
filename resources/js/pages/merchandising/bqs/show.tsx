import { Head } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import Heading from '@/components/heading';
import BackLink from '@/components/merchandising/back-link';
import { index, show } from '@/routes/merchandising/bqs';
import type {
    BqsDynamicColumn,
    BqsPackColumn,
    BqsRow,
    BqsSheetDetail,
} from '@/types';

type Props = {
    sheet: BqsSheetDetail;
    /** Derived from the rows on every request — month headers are data, not schema. */
    monthColumns: BqsDynamicColumn[];
    packColumns: BqsPackColumn[];
    rows: BqsRow[];
};

/**
 * One BQS in full.
 *
 * **The months and pack sizes are pivoted back into columns here**, which is the whole
 * point of storing them as rows: the database can hold any month range and any size
 * set, and this page reassembles whichever ones this workbook actually had. The column
 * lists arrive as props rather than being hard-coded, because they change per BQS.
 *
 * **Not paginated.** That is grouped rendering, and ARCHITECTURE.md §8.6 records that
 * grouping and pagination are incompatible — a page boundary would cut the month band
 * in half. The bound is the workbook: `bqs-import.limits.max_rows` caps the import.
 *
 * The table scrolls horizontally inside its own container; with eighteen month columns
 * it is wide by nature, and the page body must never scroll sideways.
 */
export default function BqsShow({
    sheet,
    monthColumns,
    packColumns,
    rows,
}: Props) {
    return (
        <>
            <Head title={sheet.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Returns to the list *as it was* — filters, sort and page —
                    which the breadcrumb above deliberately does not. */}
                <div>
                    <BackLink fallback={index().url} label="BQS" />
                </div>

                <Heading
                    title={sheet.title}
                    description={`${sheet.buyer} · ${sheet.fye ?? '—'} ${sheet.season ?? ''} · ${sheet.department ?? '—'}`}
                />

                {!sheet.is_current && (
                    <div role="status" className="alert alert-soft alert-info">
                        <AlertTriangle className="size-5" />
                        <span>
                            This is revision {sheet.revision_no} and has been
                            superseded by a later one.
                        </span>
                    </div>
                )}

                {sheet.warnings.length > 0 && (
                    <div
                        role="status"
                        className="alert alert-soft alert-warning"
                        data-test="bqs-warnings"
                    >
                        <AlertTriangle className="size-5" />
                        <div className="text-sm">
                            <p className="font-medium">
                                {sheet.warnings.length} warning
                                {sheet.warnings.length === 1 ? '' : 's'} while
                                reading this workbook
                            </p>
                            <ul className="mt-1 list-disc space-y-0.5 pl-4 text-xs">
                                {sheet.warnings.map((warning, index) => (
                                    <li key={index}>
                                        {warning.line !== null && (
                                            <span className="font-mono">
                                                Row {warning.line}:{' '}
                                            </span>
                                        )}
                                        {warning.message}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}

                <dl className="grid grid-cols-2 gap-4 rounded-box border border-base-300/70 p-4 sm:grid-cols-3 lg:grid-cols-6">
                    <Fact label="BQS date" value={sheet.bqs_date} />
                    <Fact label="Revision" value={String(sheet.revision_no)} />
                    <Fact
                        label="Rows"
                        value={sheet.row_count.toLocaleString()}
                    />
                    <Fact label="Worksheet" value={sheet.sheet_name} />
                    <Fact label="Imported" value={sheet.imported_at ?? '—'} />
                    {/* A fingerprint nobody has seen before means the buyer
                        changed the template — which is the signal that the
                        reader is now quietly mapping less than it used to. */}
                    <Fact
                        label="Header"
                        value={sheet.header_fingerprint}
                        mono
                    />
                </dl>

                <div className="overflow-x-auto rounded-box border border-base-300/70">
                    <table className="table table-pin-rows table-xs">
                        <thead>
                            {/* Row one mirrors the workbook's own merged bands,
                                which is how the buyer reads it. */}
                            <tr>
                                <th />
                                {/* The workbook keeps its four colour fields in
                                    the ungrouped block; they are banded here
                                    because four adjacent columns all called some
                                    kind of "colour" are unreadable otherwise. */}
                                <th colSpan={4} className="text-center">
                                    Colour
                                </th>
                                <th />
                                <th colSpan={3} className="text-center">
                                    Initial set units
                                </th>
                                <th colSpan={3} className="text-center">
                                    Total buy units
                                </th>
                                <th colSpan={2} className="text-center">
                                    Cost
                                </th>
                                {/* Planned against ordered, split because a plan is
                                    bought in two stages and one order satisfies one
                                    of them. */}
                                <th colSpan={2} className="text-center">
                                    Initial ordered
                                </th>
                                <th colSpan={2} className="text-center">
                                    Replen ordered
                                </th>
                                {packColumns.length > 0 && (
                                    <th
                                        colSpan={packColumns.length}
                                        className="text-center"
                                    >
                                        Packs
                                    </th>
                                )}
                                {monthColumns.length > 0 && (
                                    <th
                                        colSpan={monthColumns.length}
                                        className="text-center"
                                    >
                                        In DC units
                                    </th>
                                )}
                            </tr>

                            <tr>
                                <th>Style</th>
                                {/* Named in full. This column read simply
                                    "Colour" and was mistaken for missing data —
                                    with four colour fields in the source, none
                                    of them can be labelled generically. */}
                                <th>Pantone</th>
                                <th>Family</th>
                                <th>Variant</th>
                                <th>Other</th>
                                <th>On floor</th>
                                <th className="text-right">Store</th>
                                <th className="text-right">Ecomm</th>
                                <th className="text-right">OMNI</th>
                                <th className="text-right">Store</th>
                                <th className="text-right">Ecomm</th>
                                <th className="text-right">OMNI</th>
                                <th className="text-right">First</th>
                                <th className="text-right">Retail</th>
                                <th className="text-right">Units</th>
                                <th className="text-right">of plan</th>
                                <th className="text-right">Units</th>
                                <th className="text-right">of plan</th>

                                {packColumns.map((column) => (
                                    <th
                                        key={column.key}
                                        className="text-right whitespace-nowrap"
                                        title={column.pack_label}
                                    >
                                        {column.label}
                                    </th>
                                ))}

                                {monthColumns.map((column) => (
                                    <th
                                        key={column.key}
                                        className="text-right whitespace-nowrap"
                                    >
                                        {column.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id}>
                                    <td className="font-mono whitespace-nowrap">
                                        {row.vendor_style_no ?? '—'}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {row.pantone_colour ?? '—'}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {row.colour_family ?? '—'}
                                    </td>
                                    <td className="font-mono">
                                        {row.colour_variant ?? '—'}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {row.other_colour ?? '—'}
                                    </td>
                                    <td className="whitespace-nowrap">
                                        {row.on_floor_month ?? '—'}
                                    </td>

                                    <Num value={row.initial_set_units_store} />
                                    <Num value={row.initial_set_units_ecomm} />
                                    <Num value={row.initial_set_units_omni} />
                                    <Num value={row.total_buy_units_store} />
                                    <Num value={row.total_buy_units_ecomm} />
                                    <Num value={row.total_buy_units_omni} />

                                    <Money value={row.first_cost} />
                                    <Money value={row.regular_retail} />

                                    {/* OMNI, not Store: ecomm arrives as its own
                                        purchase order, so the initial orders sum to
                                        store + ecomm. On the reference documents
                                        5,502 + 266 = 5,768 = Initial Set Units /
                                        OMNI, and the replen order is 21,868 =
                                        Replenishment Units / OMNI — both exact.
                                        Against Store this reads 105%. */}
                                    <Num value={row.ordered.initial} />
                                    <Percent
                                        ordered={row.ordered.initial}
                                        planned={row.initial_set_units_omni}
                                    />
                                    <Num value={row.ordered.replen} />
                                    <Percent
                                        ordered={row.ordered.replen}
                                        planned={row.replenishment_units_omni}
                                    />

                                    {packColumns.map((column) => (
                                        <Num
                                            key={column.key}
                                            value={row.pack_sizes[column.key]}
                                        />
                                    ))}

                                    {monthColumns.map((column) => (
                                        <Num
                                            key={column.key}
                                            value={row.months[column.key]}
                                        />
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

function Fact({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-xs text-base-content/60">{label}</dt>
            <dd className={mono ? 'font-mono text-sm' : 'text-sm'}>{value}</dd>
        </div>
    );
}

/** A count. Right-aligned and tabular so columns of digits line up. */
function Num({ value }: { value: number | null | undefined }) {
    return (
        <td className="text-right tabular-nums">
            {value === null || value === undefined
                ? '—'
                : value.toLocaleString()}
        </td>
    );
}

/**
 * How much of a planned quantity has been ordered.
 *
 * Colour-coded so a row can be read at a glance: nothing ordered is neutral rather
 * than alarming — a plan awaiting its order is the normal early state — complete is
 * success, and anything **over** the plan is a warning, because ordering more than was
 * planned is a real condition somebody should look at rather than a rounding artefact.
 *
 * A plan of zero has no percentage to show; an em-dash says so instead of `Infinity`
 * or a misleading `100%`.
 */
function Percent({
    ordered,
    planned,
}: {
    ordered: number;
    planned: number | null;
}) {
    if (!planned) {
        return <td className="text-right text-base-content/40">—</td>;
    }

    const pct = Math.round((ordered / planned) * 100);

    const tone =
        pct === 0
            ? 'text-base-content/40'
            : pct > 100
              ? 'text-warning'
              : pct === 100
                ? 'text-success'
                : '';

    return <td className={`text-right tabular-nums ${tone}`}>{pct}%</td>;
}

/**
 * Money, which arrives as a **string** from a `decimal` column.
 *
 * Deliberately not parsed to a number for display beyond formatting: the point of
 * storing it as decimal is that `70711.199999999997` never becomes a float, and
 * routing it through one here would undo that at the last step.
 */
function Money({ value }: { value: string | null | undefined }) {
    if (value === null || value === undefined) {
        return <td className="text-right">—</td>;
    }

    return (
        <td className="text-right tabular-nums">
            {Number(value).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}
        </td>
    );
}

BqsShow.layout = ({ sheet }: { sheet: BqsSheetDetail }) => ({
    breadcrumbs: [
        { title: 'BQS', href: index() },
        { title: sheet.title, href: show(sheet.id) },
    ],
});
