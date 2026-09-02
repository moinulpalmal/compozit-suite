import { Head, Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import {
    buildReturnQuery,
    RETURN_PARAM,
} from '@/components/merchandising/back-link';
import DocumentUploadDialog from '@/components/merchandising/document-upload-dialog';
import ColumnFilterRow from '@/components/shared/column-filter-row';
import ListToolbar from '@/components/shared/list-toolbar';
import Pagination from '@/components/shared/pagination';
import SortableHeader, { nextSort } from '@/components/shared/sortable-header';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useListFilters } from '@/hooks/use-list-filters';
import { index, show } from '@/routes/merchandising/documents';
import type {
    DocumentFilters,
    DocumentUploadListItem,
    Filterable,
    ImportableBuyer,
    Paginated,
    StatusOption,
} from '@/types';

type Props = {
    uploads: Paginated<DocumentUploadListItem>;
    /** Empty for anyone who cannot upload — the query is not even run. */
    uploadBuyers: ImportableBuyer[];
    documentTypes: StatusOption[];
    maxFilesPerBatch: number;
    allowedExtensions: string[];
    sortable: string[];
    filterable: Filterable;
    perPageOptions: number[];
    filters: DocumentFilters;
};

/**
 * The document library: batches of files, as they arrived.
 *
 * The shared list apparatus (ARCHITECTURE.md §8.6), with no view tabs — there is only
 * one record set here. Nothing is revised and nothing fails to parse, because nothing
 * is parsed.
 *
 * **This lists batches, not files.** §8.6 records that grouped rendering and
 * pagination are incompatible, so a batch and its files cannot be one screen; the
 * files are on the detail page. `file_count` is a stored column rather than a
 * `withCount` alias precisely so this list can sort on it.
 */
export default function DocumentsIndex({
    uploads,
    uploadBuyers,
    documentTypes,
    maxFilesPerBatch,
    allowedExtensions,
    sortable,
    filterable,
    perPageOptions,
    filters,
}: Props) {
    const canUpload = useCan('merchandising.documents.create');

    const [uploadOpen, setUploadOpen] = useState(false);

    const { draft, visit, setFilter, clear, hasActiveFilter } = useListFilters({
        filters,
        url: index,
        only: ['uploads', 'filters'],
    });

    const sortProps = (column: string) => ({
        column,
        sortable,
        filters,
        onSort: (target: string) => visit(nextSort(filters, target)),
    });

    /* So the detail page comes back to *this* list rather than the top of one. */
    const back = buildReturnQuery(filters, uploads.current_page);

    const typeLabels = Object.fromEntries(
        documentTypes.map((type) => [type.value, type.label]),
    );

    return (
        <>
            <Head title="Documents" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Documents"
                        description="Files as they arrived — kept, labelled and searchable. Nothing here is read or parsed."
                    />

                    {canUpload && (
                        <Button
                            onClick={() => setUploadOpen(true)}
                            data-test="upload-documents"
                        >
                            <Upload /> Upload
                        </Button>
                    )}
                </div>

                {canUpload && (
                    <DocumentUploadDialog
                        buyers={uploadBuyers}
                        documentTypes={documentTypes}
                        maxFilesPerBatch={maxFilesPerBatch}
                        allowedExtensions={allowedExtensions}
                        open={uploadOpen}
                        onOpenChange={setUploadOpen}
                    />
                )}

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
                                <SortableHeader {...sortProps('title')}>
                                    Title
                                </SortableHeader>
                                <SortableHeader {...sortProps('file_type')}>
                                    Type
                                </SortableHeader>
                                <SortableHeader {...sortProps('file_count')}>
                                    Files
                                </SortableHeader>
                                <th>Buyer</th>
                                <th>Uploaded by</th>
                                <SortableHeader {...sortProps('created_at')}>
                                    Uploaded
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
                                        column: 'title',
                                        label: 'title',
                                    },
                                    {
                                        type: 'select',
                                        column: 'file_type',
                                        label: 'type',
                                        testId: 'document-type-filter',
                                        options: [
                                            { value: '', label: 'All' },
                                            ...documentTypes,
                                        ],
                                    },
                                    { type: 'none' },
                                    /* The column on this table is an id, and a
                                       name filter would need a join the shared
                                       apparatus does not do. The buyer scope
                                       already narrows the rows. */
                                    { type: 'none' },
                                    { type: 'none' },
                                    { type: 'none' },
                                    { type: 'none' },
                                ]}
                            />
                        </thead>

                        <tbody>
                            {uploads.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="text-center text-base-content/60"
                                    >
                                        No documents match these filters.
                                    </td>
                                </tr>
                            )}

                            {uploads.data.map((upload) => (
                                <tr key={upload.id}>
                                    <td className="max-w-64 truncate font-medium">
                                        {upload.title ?? (
                                            <span className="text-base-content/50">
                                                Untitled
                                            </span>
                                        )}
                                    </td>

                                    <td>
                                        <span className="badge badge-ghost badge-sm">
                                            {typeLabels[upload.file_type] ??
                                                upload.file_type}
                                        </span>
                                    </td>

                                    <td className="tabular-nums">
                                        {upload.file_count.toLocaleString()}
                                    </td>

                                    {/* Blank means "no particular buyer", which
                                        is a real answer and not missing data —
                                        so it is worded, not dashed. */}
                                    <td>
                                        {upload.buyer ?? (
                                            <span className="text-base-content/50">
                                                Any buyer
                                            </span>
                                        )}
                                    </td>

                                    <td>{upload.uploaded_by ?? '—'}</td>

                                    <td className="tabular-nums">
                                        {upload.created_at ?? '—'}
                                    </td>

                                    <td>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={show(upload.id, {
                                                        query: {
                                                            [RETURN_PARAM]:
                                                                back,
                                                        },
                                                    })}
                                                    data-test="open-document-upload"
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Pagination page={uploads} />
            </div>
        </>
    );
}

DocumentsIndex.layout = {
    breadcrumbs: [{ title: 'Documents', href: index() }],
};
