<?php

namespace App\Services\Merchandising\PoParser\Support;

use App\Enums\Merchandising\ParserState;

/**
 * The patterns that recognise structure in a Walmart purchase-order document.
 *
 * **These are load-bearing and were derived by trial against real documents.**
 * The document is a fixed-width text report; a pattern that looks over-specific
 * — `\d{10}` for a PO number, `\d{13}` for a UPC — is what distinguishes a field
 * from the noise around it. Changing one to be "more permissive" silently changes
 * what every extractor returns, and no test failure will point at the edit.
 *
 * Patterns that belong to exactly one extractor stay in that extractor. What lives
 * here is what more than one caller matches, or what defines the document's skeleton.
 */
final class RegexLibrary
{
    /** Starts a page, and carries both the PO number and the page number. */
    public const string PAGE_ANCHOR = '/^Purchase Order:\s*(\d{10}).*?Page:\s*(\d+)/';

    public const string PO_NUMBER = '/Purchase Order:\s*(\d{10})/';

    public const string DATE_MDY = '/(\d{2})\/(\d{2})\/(\d{4})/';

    public const string DATETIME_MDY = '/(\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2})/';

    public const string CUBIC_FEET = '/([\d,.]+)\(feet\)/';

    /** A number with an optional trailing currency code, as the summary rows carry it. */
    public const string NUMERIC_TOKEN = '/(\d{1,3}(?:,\d{3})+(?:\.\d+)?|\d+(?:\.\d+)?)(CND|USD)?/';

    /** The run of dots that marks column starts in the address block. */
    public const string GUIDE_LINE = '/^\.{3,}/';

    /**
     * The column-heading line that opens a pack's colour rows.
     *
     * **The columns between `COLOR` and `QUANTITY` vary from pack to pack**, and this
     * pattern must admit every arrangement:
     *
     * ```text
     * COLOR   SIZE   ITEM SALES CHAN   QUANTITY   ITEM NBR ...
     * COLOR          ITEM SALES CHAN   QUANTITY   ITEM NBR ...   <- a single-item pack
     * COLOR   SIZE                     QUANTITY   ITEM NBR ...
     * ```
     *
     * This previously read `/^COLOR\s+SIZE/`, which is why the middle case broke: a
     * pack with no size run never entered {@see ParserState::LineItemRows},
     * so its rows stayed in the header segment and were never read. Because
     * `PurchaseOrderBuilder::buildPacks()` pairs cost blocks to row blocks **by
     * position**, one missing row block shifted every later pack onto the *next* pack's
     * line items and left the last pack empty — a silent misattribution, not a visible
     * failure. `PoParserSingleItemPackTest` pins the shape.
     *
     * `QUANTITY` is required so that a data row beginning with a colour never matches.
     */
    public const string LINE_ITEM_COLUMNS = '/^COLOR\s{2,}.*\bQUANTITY\b/';

    /** The page footer, which resets the section state machine. */
    public const string FOOTER_TEXT = 'Wal-Mart Confidential';
}
