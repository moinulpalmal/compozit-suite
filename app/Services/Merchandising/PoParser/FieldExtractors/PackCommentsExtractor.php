<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\PackCommentsDto;
use App\Services\Merchandising\PoParser\Support\Capture;

/**
 * Reads the `PACK COMMENTS:` block that closes each pack.
 *
 * The first three fields are wrapped prose, so their patterns are `/s` and each ends
 * at the heading that follows it rather than at a line break.
 */
final class PackCommentsExtractor
{
    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     */
    public function build(array $segmentLines): PackCommentsDto
    {
        $text = Capture::joinLines($segmentLines);

        return new PackCommentsDto(
            vendorTypePico: Capture::text('/Vendor type \(PICO\)\s+(.+?)(?=Letter of Credit|\z)/s', $text),
            letterOfCredit: Capture::text('/Letter of Credit\s+(.+)$/m', $text),
            defectiveAllowanceText: Capture::text('/Defective Allowance\s+(.+?)(?=Compliance|\z)/s', $text),
            compliance: [
                'fabrics_mill' => Capture::text('/Fabrics Mill:(.+)$/m', $text),
                'yarn_mill' => Capture::text('/Yarn Mill:(.+)$/m', $text),
                'factory' => Capture::text('/Factory:(.+)$/m', $text),
            ],
        );
    }
}
