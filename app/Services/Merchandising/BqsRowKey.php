<?php

namespace App\Services\Merchandising;

use App\DataTransferObjects\Merchandising\BqsRowDto;
use App\Models\Merchandising\BqsSheet;

/**
 * The identity of one BQS line, and therefore of the BQS itself.
 *
 * ## What it is
 *
 * A sha256 over seven normalised components — FYE, season, department, vendor style,
 * pantone colour, colour variant and item description. All seven are also stored as
 * ordinary columns on `bqs_rows`, so the key is always reproducible from the row it
 * describes rather than being an opaque digest nobody can check.
 *
 * ## Why a BQS needs it
 *
 * A purchase order carries its own number, so a reissue is obvious. **A BQS workbook
 * carries nothing** — no document number, no revision date, and a `Quote ID` column
 * that is blank in every file received. There is no identifier to key a revision on,
 * so the identity is derived from the rows:
 *
 * > **Two uploads are the same BQS when their sets of row keys intersect.**
 *
 * That is the whole revision mechanism. It invents no identifier, so it cannot invent
 * a wrong one; it produces no false collision between unrelated buys that merely share
 * a season and a fine line; and because the answer is *which held sheet does this
 * workbook overlap*, it is one question per upload rather than one per row.
 *
 * A workbook overlapping **two** current sheets is refused rather than guessed at —
 * see {@see BqsImportService::collidingSheet()}. It is a revision of neither, and
 * picking one would silently orphan the other.
 *
 * @see BqsSheet the migration docblock records the same decision from the schema side
 */
class BqsRowKey
{
    /**
     * The `bqs_rows` columns the key is built from, in order.
     *
     * Order is part of the contract: change it and every stored key stops matching
     * the keys computed for the next upload, which reads as every BQS suddenly being
     * new. Adding or removing a component has the same effect.
     *
     * @var list<string>
     */
    public const array COMPONENTS = [
        'fye',
        'season',
        'department',
        'vendor_style_no',
        'pantone_colour',
        'colour_variant',
        'item_description',
    ];

    /**
     * Compute the key for a set of row values.
     *
     * Each component is lowercased and whitespace-collapsed before hashing, so a
     * workbook that gains a trailing space or a capitalised colour name still matches
     * the revision it is a reissue of. A missing component contributes an empty
     * string rather than being skipped — dropping it would make two rows differing
     * only in that component collide.
     *
     * @param  array<string, mixed>  $values
     */
    public static function for(array $values): string
    {
        $parts = array_map(
            static fn (string $component): string => BqsHeaderMap::normalise(
                is_scalar($values[$component] ?? null) ? (string) $values[$component] : ''
            ),
            self::COMPONENTS,
        );

        return hash('sha256', implode("\x1f", $parts));
    }

    /**
     * Compute the key for a row the reader has already built.
     */
    public static function forRow(BqsRowDto $row): string
    {
        return self::for($row->values);
    }
}
