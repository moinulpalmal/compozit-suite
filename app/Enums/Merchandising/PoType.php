<?php

namespace App\Enums\Merchandising;

/**
 * Walmart's numeric purchase-order type, read from the logistics block.
 *
 * The codes are Walmart's, not ours. {@see PoType::Unknown} exists because the
 * field is optional in the document and a missing value must not be an error —
 * the parser records what it found and lets validation decide whether that matters.
 */
enum PoType: int
{
    case Unknown = 0;
    case WarehouseBulk = 42;
    case RdcReplenish = 43;

    /**
     * The label rendered beside the code.
     */
    public function label(): string
    {
        return match ($this) {
            self::WarehouseBulk => __('Warehouse / Bulk'),
            self::RdcReplenish => __('RDC Replenishment'),
            self::Unknown => __('Unknown'),
        };
    }

    /**
     * Resolve a code read from a document, falling back to {@see PoType::Unknown}.
     *
     * Walmart may introduce a code this application has never seen; that is a
     * document to flag, not an exception to throw.
     */
    public static function fromCode(?int $code): self
    {
        return self::tryFrom((int) $code) ?? self::Unknown;
    }
}
