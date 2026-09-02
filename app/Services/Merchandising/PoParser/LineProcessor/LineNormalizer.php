<?php

namespace App\Services\Merchandising\PoParser\LineProcessor;

/**
 * Prepares raw extracted lines for matching, and numbers them.
 *
 * Two things happen and no more. Non-breaking spaces become ordinary ones, because
 * Word emits them freely and `\s` in a pattern would otherwise fail to match what
 * looks on screen like a space. Trailing whitespace is stripped, because an
 * end-anchored pattern (`/(.+)$/m`) is defeated by it.
 *
 * **Leading whitespace is deliberately preserved.** Column position is the document's
 * only structure — {@see ColumnDetector} slices by byte offset — so trimming the left
 * of a line would destroy the layout the whole parser depends on.
 */
final class LineNormalizer
{
    /**
     * @param  list<string>  $rawLines
     * @return list<array{index: int, text: string}>
     */
    public function normalize(array $rawLines): array
    {
        $normalized = [];

        foreach ($rawLines as $offset => $text) {
            $normalized[] = [
                'index' => $offset + 1,
                'text' => rtrim(str_replace("\u{00A0}", ' ', $text)),
            ];
        }

        return $normalized;
    }
}
