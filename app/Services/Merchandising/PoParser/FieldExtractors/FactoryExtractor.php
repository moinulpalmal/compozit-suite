<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\FactoryDto;

/**
 * Reads the `FACTORY:` block — an id, then an unlabelled address of variable length.
 *
 * Because the address has no labels, its extent is defined by what ends it: the
 * `Ship Comments:` line that follows. Blank lines inside the block are skipped rather
 * than treated as its end, since the converters emit them inconsistently between the
 * `.docx` and `.pdf` paths and the address must come out the same either way.
 */
final class FactoryExtractor
{
    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     */
    public function build(array $segmentLines): FactoryDto
    {
        $count = count($segmentLines);

        for ($offset = 0; $offset < $count; $offset++) {
            if (preg_match('/^FACTORY:\s*(\d+)/', $segmentLines[$offset]['text'], $matches) !== 1) {
                continue;
            }

            $addressLines = $this->addressLinesAfter($segmentLines, $offset + 1);

            return new FactoryDto(
                factoryId: $matches[1],
                name: $addressLines[0] ?? null,
                addressLines: $addressLines,
                countryCode: $this->countryFrom($addressLines),
            );
        }

        return new FactoryDto;
    }

    /**
     * @param  list<array{index: int, text: string}>  $segmentLines
     * @return list<string>
     */
    private function addressLinesAfter(array $segmentLines, int $start): array
    {
        $lines = [];
        $count = count($segmentLines);

        for ($offset = $start; $offset < $count; $offset++) {
            $text = trim($segmentLines[$offset]['text']);

            if ($text === '') {
                continue;
            }

            if (str_starts_with($text, 'Ship Comments:')) {
                break;
            }

            $lines[] = $text;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $addressLines
     */
    private function countryFrom(array $addressLines): ?string
    {
        if ($addressLines === []) {
            return null;
        }

        $tokens = preg_split('/\s+/', trim($addressLines[count($addressLines) - 1])) ?: [];

        return $tokens === [] ? null : $tokens[count($tokens) - 1];
    }
}
