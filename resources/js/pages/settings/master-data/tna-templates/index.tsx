import { Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import TnaTemplateController from '@/actions/App/Http/Controllers/Settings/TnaTemplateController';
import Heading from '@/components/heading';
import TnaTemplateFormDialog from '@/components/settings/tna-template-form-dialog';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ConfirmDeleteDialog from '@/components/shared/confirm-delete-dialog';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { index } from '@/routes/settings/master-data/tna-templates';
import type {
    ColorOption,
    Filterable,
    MilestoneOption,
    Paginated,
    StatusOption,
    TnaTemplateListItem,
    TnaTemplateFilters,
} from '@/types';

type Props = {
    templates: Paginated<TnaTemplateListItem>;
    statuses: StatusOption[];
    milestoneOptions: MilestoneOption[];
    colorOptions: ColorOption[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: TnaTemplateFilters;
};

/**
 * The register of TNA schedules — Settings' second master-data surface.
 *
 * Same shape as notification colours: one page with modals, `status` rather than
 * `deleted_at`, the shared list apparatus (ARCHITECTURE.md §8.6), and no permission
 * of its own.
 *
 * Ordered by band rather than by name (`TnaTemplate::defaultSort()`), because a gap
 * in the ladder is visible in band order and invisible alphabetically — and a gap is
 * exactly what leaves an order unscheduled on the TNA board.
 *
 * Delete is unconditional: nothing holds a foreign key to a template, so removing
 * one changes which schedule an order draws with and destroys no record.
 */
export default function TnaTemplatesIndex({
    templates,
    statuses,
    milestoneOptions,
    colorOptions,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const canCreate = useCan('settings.master-data.create');
    const canUpdate = useCan('settings.master-data.update');
    const canDelete = useCan('settings.master-data.delete');

    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['templates', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    const dialogProps = {
        statuses,
        milestones: milestoneOptions,
        colorOptions,
    };

    return (
        <>
            <Head title="TNA templates" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="TNA templates"
                        description="A schedule per lead-time band: when each milestone falls after the BQS date, and the colours that show how urgent it has become. Merchandising reads these to draw the TNA board."
                    />

                    {canCreate && (
                        <TnaTemplateFormDialog
                            {...dialogProps}
                            submit={TnaTemplateController.store.form()}
                            title="New TNA template"
                            description="Orders whose lead time falls in the band pick it up immediately."
                        >
                            <Button data-test="new-tna-template">
                                <Plus /> New template
                            </Button>
                        </TnaTemplateFormDialog>
                    )}
                </div>

                <ListToolbar
                    perPage={filters.per_page}
                    perPageOptions={perPageOptions}
                    onPerPage={(per_page) => visit({ per_page })}
                    onClear={clear}
                    hasActiveFilter={hasActiveFilter}
                />

                <div className="overflow-x-auto rounded-box border border-base-300/70">
                    <table className="table table-sm">
                        <thead>
                            <tr>
                                <SortableHeader {...sortProps('name')}>
                                    Name
                                </SortableHeader>
                                <SortableHeader
                                    {...sortProps('lead_time_from')}
                                >
                                    Lead time band
                                </SortableHeader>
                                <th>Milestone offsets</th>
                                <th>Colour bands</th>
                                <SortableHeader {...sortProps('status')}>
                                    Status
                                </SortableHeader>
                                <th className="w-24" />
                            </tr>

                            <ColumnFilterRow
                                filterable={filterable}
                                draft={draft}
                                onFilter={setFilter}
                                cells={[
                                    {
                                        type: 'text',
                                        column: 'name',
                                        label: 'name',
                                    },
                                    /* Not filterable: the question people ask is
                                       "which band covers 263 days?", which is a
                                       containment search across two columns, not an
                                       equality cell. See `TnaTemplate::FILTERABLE`. */
                                    { type: 'none' },
                                    { type: 'none' },
                                    { type: 'none' },
                                    {
                                        type: 'select',
                                        column: 'status',
                                        label: 'status',
                                        testId: 'status-filter',
                                        options: [
                                            { value: '', label: 'All' },
                                            ...statuses,
                                        ],
                                    },
                                    { type: 'none' },
                                ]}
                            />
                        </thead>

                        <tbody>
                            {templates.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="text-center text-base-content/60"
                                    >
                                        No TNA templates match these filters.
                                    </td>
                                </tr>
                            )}

                            {templates.data.map((template) => (
                                <tr key={template.id}>
                                    <td className="font-medium">
                                        {template.name}
                                    </td>

                                    <td className="tabular-nums">
                                        {template.lead_time_from}–
                                        {template.lead_time_to} days
                                    </td>

                                    <td className="text-xs text-base-content/70">
                                        {template.milestones.length === 0 ? (
                                            <span className="text-base-content/40">
                                                None scheduled
                                            </span>
                                        ) : (
                                            <ul>
                                                {template.milestones.map(
                                                    (milestone) => (
                                                        <li
                                                            key={
                                                                milestone.milestone
                                                            }
                                                        >
                                                            {milestone.label}
                                                            {': +'}
                                                            {
                                                                milestone.offset_days
                                                            }
                                                            {milestone.offset_days ===
                                                            1
                                                                ? ' day'
                                                                : ' days'}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                    </td>

                                    <td>
                                        {template.colors.length === 0 ? (
                                            <span className="text-xs text-base-content/40">
                                                None
                                            </span>
                                        ) : (
                                            <div className="flex flex-wrap items-center gap-1">
                                                {template.colors.map((band) => (
                                                    <span
                                                        key={
                                                            band.notification_color_id
                                                        }
                                                        className="inline-flex items-center gap-1 rounded border border-base-300 px-1.5 py-0.5 text-xs"
                                                        title={band.name}
                                                    >
                                                        <span
                                                            className="inline-block size-3 shrink-0 rounded-sm"
                                                            style={{
                                                                backgroundColor:
                                                                    band.color_code,
                                                            }}
                                                            aria-hidden="true"
                                                        />
                                                        {band.max_days_remaining ===
                                                        null
                                                            ? 'any'
                                                            : `≤ ${band.max_days_remaining}d`}
                                                    </span>
                                                ))}
                                            </div>
                                        )}
                                    </td>

                                    <td>
                                        <span
                                            className={`badge badge-sm ${template.status === 'A' ? 'badge-success' : 'badge-warning'}`}
                                        >
                                            {template.status === 'A'
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>

                                    <td>
                                        <div className="flex items-center justify-end gap-1">
                                            {canUpdate && (
                                                <TnaTemplateFormDialog
                                                    {...dialogProps}
                                                    submit={TnaTemplateController.update.form(
                                                        template.id,
                                                    )}
                                                    template={template}
                                                    title={`Edit ${template.name}`}
                                                    description="Dates are worked out on every view, so a change here reschedules every order in the band immediately — including ones already printed."
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${template.name}`}
                                                        data-test="edit-tna-template"
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                </TnaTemplateFormDialog>
                                            )}

                                            {canDelete && (
                                                <ConfirmDeleteDialog
                                                    submit={TnaTemplateController.destroy.form(
                                                        template.id,
                                                    )}
                                                    title={`Delete ${template.name}?`}
                                                    description="Orders in this band lose their schedule until another template covers them. Deactivate it instead to retire the band without a gap."
                                                    testId="delete-tna-template"
                                                />
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={templates} />
            </div>
        </>
    );
}

TnaTemplatesIndex.layout = {
    breadcrumbs: [{ title: 'TNA templates', href: index() }],
};
