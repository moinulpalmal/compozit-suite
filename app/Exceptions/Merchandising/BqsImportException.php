<?php

namespace App\Exceptions\Merchandising;

use App\Http\Controllers\Merchandising\BqsImportController;
use App\Services\Merchandising\BqsHeaderMap;
use Exception;

/**
 * A BQS workbook that cannot be imported at all.
 *
 * One type rather than a hierarchy, because there is exactly one `catch`:
 * {@see BqsImportController::store()} turns any of these into an `error` toast
 * (ARCHITECTURE.md §8.8 — refused by a rule no amount of work by the actor lifts).
 * The purchase-order parser's four exception types earn their split by being caught
 * separately; these are not, and inventing the hierarchy first would be shape without
 * a reader.
 *
 * **Every message is written to be read by whoever uploaded the file**, and names the
 * thing to fix — the missing column, the duplicated row, the two BQS revisions the
 * workbook straddles. A message a merchandiser cannot act on belongs in a log, not
 * here.
 */
class BqsImportException extends Exception
{
    /**
     * The workbook has no sheet, or none with a readable header.
     */
    public static function unreadable(string $fileName): self
    {
        return new self(__(
            'Could not read :file as a BQS workbook. It has no worksheet with a recognisable two-row header.',
            ['file' => $fileName],
        ));
    }

    /**
     * A column the row key or the buy quantity depends on is absent.
     *
     * @param  list<string>  $columns
     */
    public static function missingColumns(array $columns): self
    {
        return new self(__(
            'This workbook is missing :count required :label — :columns. Check that the header rows were not edited before it was sent.',
            [
                'count' => count($columns),
                'label' => count($columns) === 1 ? __('column') : __('columns'),
                'columns' => implode(', ', $columns),
            ],
        ));
    }

    /**
     * Two rows in one workbook describe the same style and colourway.
     *
     * Refused rather than stored, because the owner's rule is one row per
     * style + colour: a duplicate means the two rows disagree about a quantity and
     * there is no way to tell which is meant. The row key's components are listed in
     * {@see BqsHeaderMap::REQUIRED_COLUMNS}.
     */
    public static function duplicateRow(int $firstLine, int $secondLine, string $description): self
    {
        return new self(__(
            'Rows :first and :second describe the same style and colour (:description). Each style and colour may appear once.',
            ['first' => $firstLine, 'second' => $secondLine, 'description' => $description],
        ));
    }

    /**
     * The workbook's rows overlap more than one BQS already held.
     *
     * A revision of two different sheets is not a thing; accepting it would silently
     * orphan whichever was not chosen.
     *
     * @param  list<string>  $titles
     */
    public static function straddlesRevisions(array $titles): self
    {
        return new self(__(
            'This workbook overlaps :count BQS records already held (:titles), so it cannot be a revision of any one of them. Split it, or delete the ones it replaces.',
            ['count' => count($titles), 'titles' => implode(', ', $titles)],
        ));
    }

    /**
     * The workbook holds more rows than one import is allowed to write.
     */
    public static function tooManyRows(int $found, int $limit): self
    {
        return new self(__(
            'This workbook holds :found rows and the limit is :limit. Split it into smaller files.',
            ['found' => $found, 'limit' => $limit],
        ));
    }

    /**
     * The upload is neither an `.xlsx` nor an `.xls`, whatever it is named.
     */
    public static function unsupportedFormat(string $fileName): self
    {
        return new self(__(
            ':file is not an Excel workbook. A BQS must be uploaded as .xlsx or .xls.',
            ['file' => $fileName],
        ));
    }
}
