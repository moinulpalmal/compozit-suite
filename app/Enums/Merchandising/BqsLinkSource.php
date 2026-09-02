<?php

namespace App\Enums\Merchandising;

use App\Services\Merchandising\BqsPoLinker;

/**
 * Who decided that a purchase-order line belongs to a BQS row.
 *
 * The distinction is load-bearing rather than informational: {@see BqsPoLinker} will
 * overwrite an {@see self::Auto} link when the documents change, and will **never**
 * overwrite a {@see self::Manual} one. Without the column, a re-import would quietly
 * undo a person's judgement and there would be no way to tell that it had.
 */
enum BqsLinkSource: string
{
    /** The colour matched a BQS row exactly, so nobody was asked. */
    case Auto = 'auto';

    /** Somebody chose this row on the purchase-order detail page. */
    case Manual = 'manual';

    /**
     * The label rendered on the badge beside a linked colour.
     */
    public function label(): string
    {
        return match ($this) {
            self::Auto => __('Matched'),
            self::Manual => __('Linked by hand'),
        };
    }

    /**
     * Whether the matcher may replace a link carrying this source.
     */
    public function isReplaceable(): bool
    {
        return $this === self::Auto;
    }
}
