<?php

namespace App\Services\Merchandising\PoParser\LineProcessor;

/**
 * Finds where the columns of a fixed-width block start, and slices lines along them.
 *
 * The document marks its own column layout with a guide line of dot runs:
 *
 * ```text
 * VENDOR#: 28058242          BANK: 36184293        PRIMARY BENEFICIARY: 28058242
 * .......                    .....                 ....................
 * NAFA APPAREL LIMITED       EXAMPLE BANK          NAFA APPAREL LIMITED
 * ```
 *
 * Each run's first character is a column start, so the layout is read from the
 * document rather than hard-coded — which is what lets the same code handle a
 * template whose columns have moved.
 *
 * **Offsets are bytes, not characters.** `substr()` and `strlen()` are used
 * deliberately: the guide line and the data line are aligned by the converter in
 * byte space, and switching to `mb_*` would shift every cell on any line containing
 * a multi-byte character.
 */
final class ColumnDetector
{
    /**
     * The byte offset at which each column begins.
     *
     * @return list<int>
     */
    public function detect(string $guideLine, string $dotChar = '.'): array
    {
        $starts = [];
        $inRun = false;
        $length = strlen($guideLine);

        for ($offset = 0; $offset < $length; $offset++) {
            $isDot = $guideLine[$offset] === $dotChar;

            if ($isDot && ! $inRun) {
                $starts[] = $offset;
                $inRun = true;
            } elseif (! $isDot) {
                $inRun = false;
            }
        }

        return $starts;
    }

    /**
     * Cut one line into cells at the given column starts.
     *
     * @param  list<int>  $starts
     * @return list<string>
     */
    public function slice(string $line, array $starts): array
    {
        $cells = [];
        $count = count($starts);

        for ($column = 0; $column < $count; $column++) {
            $start = $starts[$column];
            $end = $starts[$column + 1] ?? strlen($line);

            $cells[] = trim(substr($line, $start, $end - $start));
        }

        return $cells;
    }
}
