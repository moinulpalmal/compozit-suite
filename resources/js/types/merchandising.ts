import type { ListFilters } from '@/types/ui';

/**
 * Merchandising module types — purchase orders. Re-exported from `@/types`.
 */

/** How much the application trusts a parsed order — `App\Enums\Merchandising\PoParseStatus`. */
export type PoParseStatus = 'success' | 'needs_review' | 'failed';

/**
 * Which record set the purchase-order list is over.
 *
 * A toolbar control rather than a filter cell, because it chooses the rows
 * rather than narrowing them — ARCHITECTURE.md §8.6.
 */
export type PurchaseOrderView = 'current' | 'revisions' | 'failed';

/** One row of the purchase-order list. */
export type PurchaseOrderListItem = {
    id: number;
    /** Walmart's ten-digit order number. */
    po_number: string;
    revision_no: number;
    is_current: boolean;
    buyer: string;
    vendor_name: string | null;
    factory_name: string | null;
    /** The document's own status word (ACTIVE, CANCELLED …), not ours. */
    document_status: string | null;
    currency: string | null;
    total_qty: number | null;
    total_cartons: number | null;
    vendor_ship_date: string | null;
    cancel_date: string | null;
    revised_at: string | null;
    parse_status: PoParseStatus;
    /** 0–1. Below the configured threshold the order is flagged for review. */
    confidence: number;
    line_items_count: number;
};

export type PurchaseOrderFilters = ListFilters & {
    view: PurchaseOrderView;
    filter: {
        po_number: string;
        vendor_name: string;
        factory_name: string;
        document_status: string;
        parse_status: string;
        currency: string;
    };
};

/** One colour/size line, as the detail page renders it out of the payload. */
export type PoLineItem = {
    color: string | null;
    size: string | null;
    quantity: number | null;
    item_number: string | null;
    vendor_stock_number: string | null;
    mfg_stock_number: string | null;
    item_description1: string | null;
    item_description2: string | null;
    upc_description: string | null;
    signing_description: string | null;
    uom_qty: number | null;
    uom_code: string | null;
    product_number: string | null;
    upc_number: string | null;
};

/** One pack of an order, out of the payload. */
export type PoPack = {
    pack_number: number | null;
    pack_description: string | null;
    subclass_fineline: string | null;
    case_upc: string | null;
    assortment_id: string | null;
    color: string | null;
    vendor_stock: string | null;
    line_items: PoLineItem[];
};

/** One thing the parser wanted a human to know about. */
export type PoWarning = {
    code: string;
    severity: 'error' | 'warning' | 'info';
    field: string;
    po_number: string | null;
    message: string;
};

/**
 * The sections that were not promoted to columns.
 *
 * This mirrors `PurchaseOrderDto::toArray()`, which is also what is stored in
 * `purchase_orders.payload` — so these keys are a contract in two directions and
 * changing one is a migration as well as a front-end change (ARCHITECTURE.md §6.7).
 */
export type PurchaseOrderPayload = {
    addresses: Record<string, Record<string, string | null> | null> | null;
    logistics: Record<string, unknown> | null;
    factory: Record<string, unknown> | null;
    tariffs: Record<string, unknown>[];
    packs: PoPack[];
    warnings: PoWarning[];
    notes: { security: string | null; acceptance_clause: string | null };
    ship_comments: Record<string, unknown> | null;
    misc_comments: Record<string, unknown> | null;
    product: Record<string, unknown> | null;
    summary: Record<string, unknown> | null;
    master_data: Record<string, unknown> | null;
    header: Record<string, unknown> | null;
};

/** One purchase order in full, as the detail page receives it. */
export type PurchaseOrderDetail = {
    id: number;
    po_number: string;
    revision_no: number;
    is_current: boolean;
    revised_at: string | null;
    revised_by: string | null;
    buyer: string;
    document_status: string | null;
    quote_id: string | null;
    po_type: string | null;
    currency: string | null;
    exchange_rate: string | null;
    vendor_name: string | null;
    factory_id: string | null;
    factory_name: string | null;
    total_cartons: number | null;
    total_qty: number | null;
    vendor_ship_date: string | null;
    cancel_date: string | null;
    parse_status: PoParseStatus;
    confidence: number;
    /** The shape of the document this was read from — a new one means the template moved. */
    template_fingerprint: string;
    source_file_name: string;
    imported_at: string | null;
    payload: PurchaseOrderPayload;
};

/** A buyer the signed-in user may import for. */
export type ImportableBuyer = {
    value: number;
    label: string;
};

/**
 * What to do with an imported order that collides with one already held —
 * `App\Enums\Merchandising\PoConflictDecision`.
 */
export type ConflictDecision = 'skip' | 'revise' | 'overwrite';

/** One decision, as the conflict step renders it. */
export type ConflictDecisionOption = {
    value: ConflictDecision;
    label: string;
    /** `overwrite` alone. Hidden from anyone without the delete permission. */
    destructive: boolean;
};

/**
 * How an order already on file compares to the one arriving.
 *
 * Both sides are shown because the question is otherwise unanswerable: a purchase
 * order number alone cannot tell a genuine reissue from a stale re-upload. `held`
 * describes the current revision; `incoming` describes the document just uploaded.
 */
export type ImportConflict = {
    po_number: string;
    held: {
        revision_no: number;
        revised_at: string | null;
        revised_by: string | null;
        total_qty: number | null;
        line_item_count: number;
        imported_at: string | null;
    };
    incoming: {
        revised_at: string | null;
        revised_by: string | null;
        total_qty: number | null;
        line_item_count: number;
    };
};

/**
 * An upload of the signed-in user's that is waiting on decisions.
 *
 * The staged rows themselves stay on the server — this carries only what the dialog
 * has to show. Present on the list page after an upload that collided, and again on
 * any later visit until it is answered.
 */
export type PendingImport = {
    id: number;
    source_file_name: string;
    /** Orders from the same document that collided with nothing and are already stored. */
    imported_count: number;
    conflicts: ImportConflict[];
};
