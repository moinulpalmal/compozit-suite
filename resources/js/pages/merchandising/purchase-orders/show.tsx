import { Head } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import Heading from '@/components/heading';
import { index, show } from '@/routes/merchandising/purchase-orders';
import type { PoPack, PoWarning, PurchaseOrderDetail } from '@/types';

/**
 * One purchase order in full.
 *
 * **Deliberately not paginated.** The packs and their line items are a tree, and
 * ARCHITECTURE.md §8.6 records that grouped rendering and pagination are
 * incompatible — a page boundary would cut a pack in half. The bound is the
 * document itself: `po-parser.limits` caps pages and orders per file at import
 * time, so a page here can only be as large as a document was allowed to be.
 *
 * Everything below the header comes out of the stored `payload`, which is the
 * `PurchaseOrderDto::toArray()` shape — see ARCHITECTURE.md §6.7 on why those
 * keys are a contract in two directions.
 */
export default function PurchaseOrderShow({
    purchaseOrder,
}: {
    purchaseOrder: PurchaseOrderDetail;
}) {
    const { payload } = purchaseOrder;
    const warnings = payload.warnings ?? [];

    return (
        <>
            <Head title={`PO ${purchaseOrder.po_number}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Purchase order ${purchaseOrder.po_number}`}
                    description={`${purchaseOrder.buyer} · revision ${purchaseOrder.revision_no}${
                        purchaseOrder.is_current
                            ? ' (current)'
                            : ' (superseded)'
                    } · imported from ${purchaseOrder.source_file_name}`}
                />

                {purchaseOrder.parse_status === 'failed' && (
                    <div role="alert" className="alert alert-soft alert-error">
                        <AlertTriangle className="size-5" />
                        <span>
                            This order did not parse cleanly and is kept only so
                            its warnings can be read. Do not rely on the figures
                            below — re-import the document once the cause is
                            fixed.
                        </span>
                    </div>
                )}

                <Facts order={purchaseOrder} />

                {warnings.length > 0 && <Warnings warnings={warnings} />}

                <section className="space-y-4">
                    <h2 className="text-base font-semibold">
                        Packs and line items
                    </h2>

                    {payload.packs?.map((pack) => (
                        <Pack key={pack.pack_number} pack={pack} />
                    ))}
                </section>
            </div>
        </>
    );
}

/** The header fields, which are real columns rather than payload. */
function Facts({ order }: { order: PurchaseOrderDetail }) {
    const facts: [string, string | number | null][] = [
        ['Document status', order.document_status],
        ['Quote ID', order.quote_id],
        ['PO type', order.po_type],
        ['Vendor', order.vendor_name],
        ['Factory', order.factory_name],
        ['Factory ID', order.factory_id],
        ['Currency', order.currency],
        ['Exchange rate', order.exchange_rate],
        ['Ship date', order.vendor_ship_date],
        ['Cancel date', order.cancel_date],
        ['Total cartons', order.total_cartons],
        ['Total quantity', order.total_qty],
        ['Revised', order.revised_at],
        ['Revised by', order.revised_by],
        ['Imported', order.imported_at],
        /* A fingerprint nobody has seen before means Walmart moved the
           template, which is the signal the extractors are now reading less. */
        ['Template', order.template_fingerprint],
    ];

    return (
        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 rounded-box border border-base-300/70 p-4 sm:grid-cols-3 lg:grid-cols-4">
            {facts.map(([label, value]) => (
                <div key={label}>
                    <dt className="text-xs text-base-content/60">{label}</dt>
                    <dd className="text-sm font-medium">
                        {value === null || value === '' ? '—' : value}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function Warnings({ warnings }: { warnings: PoWarning[] }) {
    return (
        <section className="space-y-2">
            <h2 className="text-base font-semibold">
                Parser warnings ({warnings.length})
            </h2>

            <ul className="space-y-1">
                {warnings.map((warning, position) => (
                    <li
                        key={`${warning.code}-${position}`}
                        className={`alert alert-soft py-2 text-sm ${
                            warning.severity === 'error'
                                ? 'alert-error'
                                : warning.severity === 'warning'
                                  ? 'alert-warning'
                                  : 'alert-info'
                        }`}
                    >
                        <span>
                            <span className="font-mono text-xs">
                                {warning.code}
                            </span>{' '}
                            {warning.message}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Pack({ pack }: { pack: PoPack }) {
    return (
        <div className="overflow-hidden rounded-box border border-base-300/70">
            <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1 bg-base-200/50 px-4 py-2">
                <span className="font-semibold">Pack {pack.pack_number}</span>
                {pack.pack_description && (
                    <span className="text-sm">{pack.pack_description}</span>
                )}
                {pack.color && (
                    <span className="badge badge-ghost badge-sm">
                        {pack.color}
                    </span>
                )}
                {pack.vendor_stock && (
                    <span className="text-xs text-base-content/60">
                        Vendor stock {pack.vendor_stock}
                    </span>
                )}
            </div>

            {/* Wide content scrolls inside its own container, never the page. */}
            <div className="overflow-x-auto">
                <table className="table table-sm">
                    <thead>
                        <tr>
                            <th>Colour</th>
                            <th>Size</th>
                            <th className="text-right">Quantity</th>
                            <th>Item no.</th>
                            <th>Vendor stock</th>
                            <th>Product no.</th>
                            <th>UPC</th>
                            <th>Description</th>
                            <th>UOM</th>
                        </tr>
                    </thead>
                    <tbody>
                        {pack.line_items?.map((item) => (
                            <tr key={`${item.item_number}-${item.size}`}>
                                <td>{item.color ?? '—'}</td>
                                <td>{item.size ?? '—'}</td>
                                <td className="text-right tabular-nums">
                                    {item.quantity?.toLocaleString() ?? '—'}
                                </td>
                                <td className="font-mono text-xs">
                                    {item.item_number ?? '—'}
                                </td>
                                <td className="font-mono text-xs">
                                    {item.vendor_stock_number ?? '—'}
                                </td>
                                <td className="font-mono text-xs">
                                    {item.product_number ?? '—'}
                                </td>
                                <td className="font-mono text-xs">
                                    {item.upc_number ?? '—'}
                                </td>
                                <td className="max-w-64 truncate">
                                    {item.item_description1 ?? '—'}
                                </td>
                                <td>{item.uom_code ?? '—'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

PurchaseOrderShow.layout = ({
    purchaseOrder,
}: {
    purchaseOrder: PurchaseOrderDetail;
}) => ({
    breadcrumbs: [
        { title: 'Purchase orders', href: index() },
        { title: purchaseOrder.po_number, href: show(purchaseOrder.id) },
    ],
});
