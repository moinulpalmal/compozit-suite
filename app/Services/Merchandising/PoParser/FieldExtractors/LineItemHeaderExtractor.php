<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\LineItemHeaderDto;
use App\Services\Merchandising\PoParser\Support\Capture;

/**
 * Reads the block above a pack's colour/size lines.
 *
 * `total_cartons_per_line` is the one field with a consumer beyond display:
 * validation rule V3 sums it across packs and compares the total against the order's
 * own master-carton figure.
 *
 * The quoted cost is captured twice, once per currency, because Walmart prints
 * whichever applies rather than both — so these are two separate optional labels, not
 * a pair.
 */
final class LineItemHeaderExtractor
{
    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     */
    public function build(array $segmentLines): LineItemHeaderDto
    {
        $text = Capture::joinLines($segmentLines);

        $fields = [
            'hang_tag' => Capture::text('/HANG TAG\s*:\s*(.+)$/m', $text),
            'total_cartons_per_line' => Capture::int('/Total Cartons per Line:\s*(\d+)/', $text),
            'quoted_each_cost_usd' => Capture::float('/Quoted each Cost:\s*([\d.]+)\s*USD/', $text),
            'quoted_each_cost_cnd' => Capture::float('/Quoted each Cost:\s*([\d.]+)\s*CND/', $text),
            'assortment_id' => Capture::text('/Assortment Id:\s*(\d+)/', $text),
            'case_id' => Capture::text('/Case Id:\s*(\S*)/', $text),
            'vendor_stock' => Capture::text('/Vendor Stock:\s*(\S+)/', $text),
            'assortment_indicator' => Capture::text('/Assortment Ind:\s*(.+)$/m', $text),
        ];

        $dimensions = $this->itemDimensions($text);

        if ($dimensions !== null) {
            $fields['item_dimensions'] = $dimensions;
        }

        return new LineItemHeaderDto($fields);
    }

    /**
     * Length, width and height, printed as three numbers after one label.
     *
     * @return array{0: float, 1: float, 2: float}|null
     */
    private function itemDimensions(string $text): ?array
    {
        if (preg_match('/Item \(L x W x H\):\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)/', $text, $matches) !== 1) {
            return null;
        }

        return [(float) $matches[1], (float) $matches[2], (float) $matches[3]];
    }
}
