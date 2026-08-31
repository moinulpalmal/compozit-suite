<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\ProductDto;
use App\Services\Merchandising\PoParser\Support\Capture;

/**
 * Reads the `PRODUCT:` block.
 *
 * The name is bounded by the `PRODUCT #` label that follows it on the same row rather
 * than by a space count, because product names contain runs of spaces of their own.
 */
final class ProductExtractor
{
    public function build(string $text): ProductDto
    {
        return new ProductDto(
            name: Capture::text('/PRODUCT:\s*(.+?)\s{2,}PRODUCT #/', $text),
            productNumber: Capture::int('/PRODUCT #:\s*(\d+)/', $text),
            classification: Capture::text('/^PRODUCT CLASSIFICATION:\s*(.+)$/m', $text),
        );
    }
}
