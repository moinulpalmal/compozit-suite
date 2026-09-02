<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\LineItemDto;
use App\Services\Merchandising\BqsColourMatch;
use App\Services\Merchandising\PoParser\LineProcessor\ColumnDetector;
use App\Services\Merchandising\PoParser\Support\NumberParser;
use App\Services\Merchandising\PoParser\Support\RegexLibrary;
use App\Services\Merchandising\PoParser\Validators\PoDataValidator;

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
 *
 * ## Colour and size come from the pack's own column headings
 *
 * The segment opens with the heading line that put the state machine here
 * ({@see RegexLibrary::LINE_ITEM_COLUMNS}), and that line states the layout:
 *
 * ```text
 * COLOR           SIZE             ITEM SALES CHAN      QUANTITY     ITEM NBR
 * 0               16               33                   51           64
 * GRAY-SIRO MIX   3-6M             OMNI CHANNEL IT             2     050087781
 * ```
 *
 * Each field is read from the span between its own heading and the next one, so a
 * pack that prints no `SIZE` column is read correctly rather than guessed at, and the
 * `ITEM SALES CHAN` column between them is never mistaken for part of the size.
 *
 * **Colour is read independently of size.** It previously was not: the colour was
 * "everything to the left of the size", so a row whose size was not recognised
 * returned `[null, null]` and lost its colour too. That is what left every line of an
 * infant purchase order with no colour, and therefore unlinkable to its BQS row — see
 * {@see BqsColourMatch}.
 *
 * The vocabulary is only consulted when the headings cannot be read, and then only
 * anchored to a whitespace boundary. It contains bare `S`, `M` and `L`, so an
 * unanchored search finds the `S` in `RED-JESTER RED` and reports the colour as
 * `RED-JE`. `PoParserSingleItemPackTest` pins that precisely.
 */
final class LineItemRowExtractor
{
    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     * @param  list<string>  $sizeVocabulary  fallback labels, from the buyer's BQS
     * @return list<LineItemDto>
     */
    public function build(array $segmentLines, array $sizeVocabulary = []): array
    {
        $columns = $this->columnSpans($segmentLines);
        $sizePattern = $this->sizePattern($sizeVocabulary);
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
                $columns,
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

    /**
     * @param  array{color: array{0: int, 1: int|null}, size: array{0: int, 1: int|null}}|null  $columns
     */
    private function toDto(
        string $firstLine,
        string $secondLine,
        string $thirdLine,
        string $sizePattern,
        ?array $columns,
    ): LineItemDto {
        // pdftotext -layout can break a 13-digit code across a space ("0000024 640806").
        // The identifier patterns run against a repaired copy; colour, size and
        // quantity still read the original, whose spacing carries their column layout.
        $repaired = (string) preg_replace_callback(
            '/(?<!\d)(\d{7})\s+(\d{6})(?!\d)/',
            static fn (array $matches): string => $matches[1].$matches[2],
            $firstLine,
        );

        [$color, $size] = $this->colorAndSize($firstLine, $sizePattern, $columns);
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
     * Where the `COLOR` and `SIZE` columns begin and end, read from the heading line.
     *
     * The heading is the first line of the segment — the state machine puts the line
     * that triggered the transition into the segment it opens. Headings are separated
     * by runs of two or more spaces, which is also what allows a two-word heading like
     * `ITEM SALES CHAN` to stay whole.
     *
     * A span ends where the *next* heading begins, whatever that heading is. Cutting
     * `SIZE` at `QUANTITY` instead would swallow the `ITEM SALES CHAN` column that sits
     * between them on some packs.
     *
     * **Offsets are bytes.** `substr()` and `strlen()` throughout, for the reason
     * {@see ColumnDetector} gives:
     * the converter aligns the heading and the data line in byte space, and `mb_*`
     * would shift every cell on any line carrying a multi-byte character.
     *
     * @param  list<array{index: int, text: string}>  $segmentLines
     * @return array{color: array{0: int, 1: int|null}, size: array{0: int, 1: int|null}}|null
     */
    private function columnSpans(array $segmentLines): ?array
    {
        $heading = null;

        foreach ($segmentLines as $line) {
            if (preg_match(RegexLibrary::LINE_ITEM_COLUMNS, $line['text']) === 1) {
                $heading = $line['text'];

                break;
            }
        }

        if ($heading === null) {
            return null;
        }

        $parts = preg_split('/\s{2,}/', $heading, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);

        if ($parts === false || $parts === []) {
            return null;
        }

        $starts = [];

        foreach ($parts as [$label, $at]) {
            $starts[] = ['label' => strtoupper(trim($label)), 'at' => $at];
        }

        $spanFor = static function (string $label) use ($starts): ?array {
            foreach ($starts as $index => $heading) {
                if ($heading['label'] === $label) {
                    return [$heading['at'], $starts[$index + 1]['at'] ?? null];
                }
            }

            return null;
        };

        $color = $spanFor('COLOR');

        if ($color === null) {
            return null;
        }

        return ['color' => $color, 'size' => $spanFor('SIZE') ?? [0, 0]];
    }

    /**
     * The size labels this buyer's BQS knows, as one anchored alternation.
     *
     * Only a fallback — see the class docblock. A size is an arbitrary label
     * (`M(7/8)`, `XL(14-16)`, `18-24M`) with no shape that distinguishes it from the
     * colour text beside it, so it can only be recognised by name.
     *
     * **The alternation is anchored to a column boundary**: a size is preceded by the
     * start of the line or two or more spaces, and followed by two or more spaces or
     * the end of the line, because that is what a fixed-width column guarantees.
     * Without the anchors the leftmost match wins and the bare `S` in the vocabulary
     * matches inside `RED-JESTER RED`.
     *
     * Longest first, so `12-18M` is preferred over a vocabulary that also holds `M`
     * at the same offset.
     *
     * @param  list<string>  $vocabulary
     */
    private function sizePattern(array $vocabulary): string
    {
        if ($vocabulary === []) {
            /** @var list<string> $vocabulary */
            $vocabulary = config('po-parser.parsing.size_vocab', []);
        }

        usort($vocabulary, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $alternatives = array_map(
            static fn (string $size): string => preg_quote($size, '/'),
            array_values(array_unique($vocabulary)),
        );

        if ($alternatives === []) {
            return '';
        }

        return '/(?:^|\s{2,})('.implode('|', $alternatives).')(?=\s{2,}|$)/';
    }

    /**
     * Read the colour and the size, each on its own terms.
     *
     * A null size is an ordinary outcome — a single-item pack prints no size column —
     * and it never costs the row its colour. {@see PoDataValidator}
     * decides whether either absence matters.
     *
     * @param  array{color: array{0: int, 1: int|null}, size: array{0: int, 1: int|null}}|null  $columns
     * @return array{0: string|null, 1: string|null}
     */
    private function colorAndSize(string $line, string $sizePattern, ?array $columns): array
    {
        if ($columns !== null) {
            return [
                $this->cell($line, $columns['color']),
                $this->cell($line, $columns['size']),
            ];
        }

        /* No heading to read: fall back to naming the size, and keep the colour either way. */
        $size = $sizePattern !== '' && preg_match($sizePattern, $line, $matches) === 1
            ? $matches[1]
            : null;

        return [$this->leadingCell($line), $size];
    }

    /**
     * One column's text, or null when the span is empty or off the end of the line.
     *
     * @param  array{0: int, 1: int|null}  $span
     */
    private function cell(string $line, array $span): ?string
    {
        [$start, $end] = $span;

        if ($end !== null && $end <= $start) {
            return null;
        }

        $text = $end === null
            ? substr($line, $start)
            : substr($line, $start, $end - $start);

        $text = trim($text);

        return $text === '' ? null : $text;
    }

    /**
     * The colour when there is no heading to measure the columns against.
     *
     * The first cell of the row, which is where the colour sits. A cell ends at the
     * first run of **two or more** spaces — a colour holds single spaces of its own
     * (`RED-JESTER RED`, `GRAY-SIRO MIX`), so a single space cannot end one.
     *
     * Taking everything up to the quantity instead would fold the size and the
     * `ITEM SALES CHAN` column into the colour on exactly the rows this fallback
     * exists to rescue.
     */
    private function leadingCell(string $line): ?string
    {
        $parts = preg_split('/\s{2,}/', $line, 2);
        $color = trim($parts[0] ?? '');

        return $color === '' ? null : $color;
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
