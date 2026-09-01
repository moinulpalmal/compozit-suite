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

/*
|--------------------------------------------------------------------------
| BQS — the buyer's buy plan workbook
|--------------------------------------------------------------------------
|
| A BQS is uploaded as an `.xlsx`, not typed. The types below deliberately do
| not reuse the purchase-order ones even where they look alike: a workbook is
| **one** BQS answered with **one** decision, whereas a document holds up to
| fifty orders answered one at a time. Sharing `PendingImport` between them
| would mean a field that is a list on one side and a scalar on the other.
|
*/

/** How much the application trusts an imported BQS — `App\Enums\Merchandising\BqsParseStatus`. */
export type BqsParseStatus = 'success' | 'needs_review' | 'failed';

/** Which record set the BQS list is over. A toolbar control, not a filter cell. */
export type BqsView = 'current' | 'revisions';

/** Which of the two pack bands a size quantity came from. */
export type BqsPackType = 'break' | 'case';

/** What to do with an uploaded BQS that overlaps one already held. */
export type BqsConflictDecision = 'skip' | 'revise' | 'overwrite';

/** One decision, as the conflict step renders it. */
export type BqsConflictDecisionOption = {
    value: BqsConflictDecision;
    label: string;
    /** `overwrite` alone. Hidden from anyone without the delete permission. */
    destructive: boolean;
};

/** One row of the BQS list. */
export type BqsListItem = {
    id: number;
    /** The workbook's file name — the only human label a BQS has. */
    title: string;
    /** Entered on the upload form; the workbook carries no date. */
    bqs_date: string;
    buyer: string;
    fye: string | null;
    season: string | null;
    department: string | null;
    revision_no: number;
    is_current: boolean;
    row_count: number;
    parse_status: BqsParseStatus;
};

export type BqsFilters = ListFilters & {
    view: BqsView;
    filter: {
        title: string;
        fye: string;
        season: string;
        department: string;
        bqs_date: string;
        parse_status: string;
    };
};

/**
 * A warning raised while reading a workbook.
 *
 * Kept next to the import that produced it rather than reported once in a toast:
 * an unmapped column or a row whose own totals disagree is worth finding later.
 */
export type BqsWarning = {
    severity: string;
    message: string;
    /** The workbook row it came from, when it came from one. */
    line: number | null;
};

/**
 * One `In DC Units` or pack column, derived from the rows rather than declared.
 *
 * These are exactly the part of a BQS that is **not** schema — month names and size
 * labels change with every season — so the detail page is told what its columns are
 * on every request.
 */
export type BqsDynamicColumn = {
    key: string;
    label: string;
};

export type BqsPackColumn = BqsDynamicColumn & {
    pack_type: BqsPackType;
    pack_label: string;
};

/** One line of a BQS, as the detail table renders it. */
export type BqsRow = {
    id: number;
    line_no: number;
    vendor_style_no: string | null;
    item_description: string | null;
    /* All four of the workbook's colour fields. `other_colour` is empty in every
       file received so far, and is rendered anyway — the column exists in the
       source, and a silently dropped field is worse than a column of dashes. */
    pantone_colour: string | null;
    colour_family: string | null;
    colour_variant: string | null;
    other_colour: string | null;
    /** What has actually been ordered against this plan line. */
    ordered: BqsRowOrdered;
    total_stores: number | null;
    initial_set_units_store: number | null;
    initial_set_units_ecomm: number | null;
    initial_set_units_omni: number | null;
    total_buy_units_store: number | null;
    total_buy_units_ecomm: number | null;
    total_buy_units_omni: number | null;
    replenishment_units_store: number | null;
    replenishment_units_omni: number | null;
    first_cost: string | null;
    regular_retail: string | null;
    total_buy_dollar_omni: string | null;
    on_floor_month: string | null;
    wm_wk_in_store: string | null;
    vendor_name: string | null;
    country_of_origin: string | null;
    /** Keyed by `monthColumns[].key`. */
    months: Record<string, number | null>;
    /** Keyed by `packColumns[].key`. */
    pack_sizes: Record<string, number | null>;
    [column: string]: unknown;
};

/** One BQS in full, as the detail page renders it. */
export type BqsSheetDetail = {
    id: number;
    title: string;
    bqs_date: string;
    buyer: string;
    fye: string | null;
    season: string | null;
    department: string | null;
    revision_no: number;
    is_current: boolean;
    row_count: number;
    parse_status: BqsParseStatus;
    source_file_name: string;
    sheet_name: string;
    /** Changes when George changes the template — see `BqsHeaderMap`. */
    header_fingerprint: string;
    imported_at: string | null;
    warnings: BqsWarning[];
};

/** Who decided a purchase-order colour belongs to a BQS row. */
export type BqsLinkSource = 'auto' | 'manual';

/**
 * One colour of a purchase order, and the BQS row it was planned by.
 *
 * **Grouped by colour rather than by line item.** A pack is one colour in five sizes
 * and all five belong to the same plan row, so the decision is asked once per colour —
 * four times on the reference document rather than sixty.
 */
export type BqsColourLink = {
    /** `vendor_stock|color`, matching how the server grouped them. */
    key: string;
    vendor_stock: string | null;
    color: string | null;
    bqs_row_id: number | null;
    bqs_sheet_id: number | null;
    /** `PANTONE (FAMILY)`, or null when nothing is linked. */
    label: string | null;
    source: BqsLinkSource | null;
    /** `quantity × total_cartons_per_line`, summed — never the raw quantity. */
    ordered_units: number;
    /**
     * BQS rows of the same style and buyer; empty when the plan has none.
     *
     * `ComboboxOption`-shaped, so `hint` is omitted rather than null when a row has
     * no colour variant — that type accepts `string | undefined`, not null.
     */
    candidates: { value: number; label: string; hint?: string }[];
};

/**
 * What has been ordered against one BQS row.
 *
 * Split by purchase-order type, because a plan is bought in two stages and one order
 * satisfies one of them — a PO covering the whole initial buy reads as 22% of the
 * total, which looks behind schedule when it is complete.
 */
export type BqsRowOrdered = {
    initial: number;
    replen: number;
    /** Orders whose type matches neither code the BQS row names. */
    other: number;
    po_numbers: string[];
};

/**
 * A BQS upload of the signed-in user's that is waiting on a decision.
 *
 * One collision, not a list of them: a workbook is one BQS, and it is the *rows*
 * whose keys overlap that identify it — see `App\Services\Merchandising\BqsRowKey`.
 */
export type BqsPendingImport = {
    id: number;
    source_file_name: string;
    bqs_date: string;
    row_count: number;
    /** The held BQS this workbook overlaps. */
    collides_with_title: string;
    collides_with_revision: number;
    /** How many of the incoming rows already exist on that revision. */
    overlapping_rows: number;
    warnings: BqsWarning[];
};
