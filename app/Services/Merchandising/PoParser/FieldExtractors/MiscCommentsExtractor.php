<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\MiscCommentsDto;
use App\Services\Merchandising\PoParser\Support\Capture;

/**
 * Reads the optional `Misc comments` block.
 *
 * Returns null — not an empty object — when the block is absent or carries no text,
 * so that "the order had no misc comments" stays distinguishable from "the block was
 * there and we read nothing out of it". Most orders take the first path.
 */
final class MiscCommentsExtractor
{
    public function build(string $text): ?MiscCommentsDto
    {
        if (trim($text) === '') {
            return null;
        }

        $raw = Capture::text('/Misc comments\s+(.+)$/m', $text);

        if ($raw === null) {
            return null;
        }

        return new MiscCommentsDto(
            raw: $raw,
            ceCaseNumber: Capture::text('/C&E CASE#\s*(\d+)/', $text),
            discrepancyType: Capture::text('/DISCREPANCY TYPE:\s*(\w+)/', $text),
            updatedBy: Capture::text('/UPDATED BY\s+(\w+)/', $text),
        );
    }
}
