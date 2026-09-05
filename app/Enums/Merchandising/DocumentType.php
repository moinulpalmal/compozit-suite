<?php

namespace App\Enums\Merchandising;

use App\Services\Merchandising\BqsImportService;
use App\Services\Merchandising\DocumentLibraryService;

/**
 * What an uploaded document *is*, as the person who uploaded it describes it.
 *
 * **This is a label, not a format and not a pipeline.** Nothing in the document
 * library reads a file, so {@see self::Bqs} here means "somebody called this a BQS",
 * not "this workbook was imported as a BQS". A real BQS import produces `bqs_sheets`
 * rows through {@see BqsImportService} and is a different
 * surface with a different permission; see ARCHITECTURE.md §5, Module 3.
 *
 * That distinction is the reason this enum exists rather than a free-text field: the
 * five values are how a merchandiser finds a file again, and a filter cell needs a
 * closed set. {@see self::Other} is deliberately part of it — an inbox that refuses
 * the documents nobody has a name for is an inbox people stop using.
 *
 * Applied by {@see DocumentLibraryService}.
 */
enum DocumentType: string
{
    /** A buy plan workbook, as the buyer sent it. */
    case Bqs = 'bqs';

    /** A buyer's purchase-order document. */
    case PurchaseOrder = 'purchase-order';

    /** A size chart or measurement specification. */
    case SizeChart = 'size-chart';

    /** A time-and-action formula or lead-time working sheet. */
    case TnaFormula = 'tna-formula';

    /** Anything else worth keeping. */
    case Other = 'other';

    /**
     * The label rendered in a type cell.
     */
    public function label(): string
    {
        return match ($this) {
            self::Bqs => __('BQS'),
            self::PurchaseOrder => __('Purchase order'),
            self::SizeChart => __('Size chart'),
            self::TnaFormula => __('TNA formula'),
            self::Other => __('Other'),
        };
    }

    /**
     * Every case as the option list a combobox or filter cell renders.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
