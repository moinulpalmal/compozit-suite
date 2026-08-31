<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\LineItemDto;
use App\Services\Merchandising\PoParser\Support\NumberParser;

/**
 * Reads the colour/size rows — the only part of the document that becomes database
 * rows rather than JSON.
 *
 * **One line item spans three printed lines**, and they are not adjacent in every
 * format. The first carries colour, size, quantity and the numeric identifiers; the
 * second the vendor stock number and two descriptions; the third the UPC and signing
 * descriptions and the unit of measure. `pdftotext -layout` inserts blank separators
 * between groups where the `.docx` path does not, so lines two and three are found by
 * skipping blanks rather than by adding one and two to the index. Getting that wrong
 * silently pairs each item with the *next* item's descriptions.
 *
 * A first line is recognised by carrying a 13-digit code. Anything else in the
 * segment — headings, rules, stray text — is skipped rather than parsed into a
 * half-empty item.
 */
final class LineItemRowExtractor
{
    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     * @return list<LineItemDto>
     */
    public function build(array $segmentLines): array
    {
        $sizePattern = $this->sizePattern();
        $items = [];
        $count = count($segmentLines);
        $offset = 0;

        while ($offset < $count) {
            $firstLine = $segmentLines[$offset]['text'];

            if (preg_match('/\d{13}/', $firstLine) !== 1) {
                $offset++;

                continue;
            }

            $secondOffset = $this->nextNonBlank($segmentLines, $offset + 1, $count);

            if ($secondOffset === null) {
                break;
            }

            $thirdOffset = $this->nextNonBlank($segmentLines, $secondOffset + 1, $count);

            if ($thirdOffset === null) {
                break;
            }

            $items[] = $this->toDto(
                $firstLine,
                $segmentLines[$secondOffset]['text'],
                $segmentLines[$thirdOffset]['text'],
                $sizePattern,
            );

            $offset = $thirdOffset + 1;
        }

        return $items;
    }

    /**
     * @param  list<array{index: int, text: string}>  $lines
     */
    private function nextNonBlank(array $lines, int $from, int $count): ?int
    {
        for ($offset = $from; $offset < $count; $offset++) {
            if (trim($lines[$offset]['text']) !== '') {
                return $offset;
            }
        }

        return null;
    }

    private function toDto(string $firstLine, string $secondLine, string $thirdLine, string $sizePattern): LineItemDto
    {
        // pdftotext -layout can break a 13-digit code across a space ("0000024 640806").
        // The identifier patterns run against a repaired copy; colour, size and
        // quantity still read the original, whose spacing carries their column layout.
        $repaired = (string) preg_replace_callback(
            '/(?<!\d)(\d{7})\s+(\d{6})(?!\d)/',
            static fn (array $matches): string => $matches[1].$matches[2],
            $firstLine,
        );

        [$color, $size] = $this->colorAndSize($firstLine, $sizePattern);
        [$quantity, $itemNumber] = $this->quantityAndItemNumber($firstLine);
        [$productNumber, $upcNumber] = $this->productAndUpc($repaired);
        [$vendorStock, $description1, $description2] = $this->vendorAndDescriptions($secondLine);
        [$upcDescription, $signingDescription, $uomQty, $uomCode] = $this->descriptionsAndUom($thirdLine);

        return new LineItemDto(
            color: $color,
            size: $size,
            quantity: $quantity,
            itemNumber: $itemNumber,
            vendorStockNumber: $vendorStock,
            mfgStockNumber: $this->mfgStockNumber($repaired),
            itemDescription1: $description1,
            itemDescription2: $description2,
            upcDescription: $upcDescription,
            signingDescription: $signingDescription,
            uomQty: $uomQty,
            uomCode: $uomCode,
            productNumber: $productNumber,
            upcNumber: $upcNumber,
        );
    }

    /**
     * The size labels Walmart prints, as one alternation.
     *
     * They are configured rather than inferred because a size is an arbitrary label
     * — `M(7/8)`, `XL(14-16)` — with no pattern that separates it from the colour
     * text beside it. A size absent from the vocabulary is not recognised, and the
     * colour then absorbs it.
     */
    private function sizePattern(): string
    {
        /** @var list<string> $vocabulary */
        $vocabulary = config('po-parser.parsing.size_vocab', []);

        $alternatives = array_map(
            static fn (string $size): string => preg_quote($size, '/'),
            $vocabulary,
        );

        return '/('.implode('|', $alternatives).')/';
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function colorAndSize(string $line, string $sizePattern): array
    {
        if (preg_match($sizePattern, $line, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [null, null];
        }

        // The colour is everything to the left of the size, which is the only
        // boundary the row offers.
        return [trim(substr($line, 0, $matches[1][1])), $matches[1][0]];
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private function quantityAndItemNumber(string $line): array
    {
        if (preg_match('/(\d+)\s+(\d{9})/', $line, $matches) !== 1) {
            return [null, null];
        }

        return [(int) $matches[1], $matches[2]];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function productAndUpc(string $repaired): array
    {
        if (preg_match('/(\d{13})\s+(\d{13})/', $repaired, $matches) !== 1) {
            return [null, null];
        }

        return [$matches[1], $matches[2]];
    }

    /**
     * The manufacturer's stock number, which sits between the item number and the
     * two 13-digit codes and has no label of its own.
     */
    private function mfgStockNumber(string $repaired): ?string
    {
        if (preg_match('/(\d{9})\s+([A-Z0-9]{6,13})\s+(\d{13})\s+(\d{13})/', $repaired, $matches) !== 1) {
            return null;
        }

        return $matches[2];
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function vendorAndDescriptions(string $line): array
    {
        if (preg_match('/([A-Z0-9]{10})/', $line, $matches) !== 1) {
            return [null, null, null];
        }

        $stock = $matches[1];
        $rest = substr($line, (int) strpos($line, $stock) + strlen($stock));
        $parts = preg_split('/\s{2,}/', trim($rest)) ?: [];

        return [
            $stock,
            isset($parts[0]) ? trim($parts[0]) : null,
            isset($parts[1]) ? trim($parts[1]) : null,
        ];
    }

    /**
     * @return array{0: string|null, 1: string|null, 2: float|null, 3: string|null}
     */
    private function descriptionsAndUom(string $line): array
    {
        if (preg_match('/([\d.]+)\s+([A-Z]{2})\s*$/', trim($line), $matches) !== 1) {
            return [null, null, null, null];
        }

        $head = substr($line, 0, (int) strpos($line, $matches[1]));
        $parts = preg_split('/\s{2,}/', trim($head)) ?: [];

        return [
            isset($parts[0]) ? trim($parts[0]) : null,
            isset($parts[1]) ? trim($parts[1]) : null,
            NumberParser::parse($matches[1]),
            $matches[2],
        ];
    }
}
