<?php

namespace App\Services\Merchandising;

use App\Enums\Merchandising\BqsPackType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Turns a BQS workbook's two header rows into a column-letter → field map.
 *
 * ## Why the header takes two rows to read
 *
 * Row 1 is a band of merged group labels; row 2 is the leaf header. Neither is
 * sufficient alone: `Store`, `Ecomm` and `OMNI` each appear **six times** in row 2,
 * under six different row-1 bands. A column is therefore identified by the pair, and
 * the field it maps to is named `{band}_{leaf}`.
 *
 * Because row 1's labels are *merged* across their band, only the first column of each
 * band carries the text; the rest read empty and inherit it. {@see self::bandFor()} is
 * where that carry-forward happens.
 *
 * ## Static columns versus dynamic bands
 *
 * Three bands are **dynamic** and are never matched against a fixed list:
 *
 * - `In DC Units` — headed with month names (`November-2026 … April-2028`)
 * - `Break Packs` / `Case Packs` — headed with size labels (`XS(4/5) … XL(14/16)`)
 *
 * Those headers change with every season and every size range. They become rows in
 * `bqs_row_months` and `bqs_row_pack_sizes`, so the reader only needs to know *which
 * band a column is in*, not what it is called.
 *
 * Everything else is matched **by name, in any order**. Positional reading was
 * considered and rejected: one inserted column would silently shift 89 fields and
 * write wrong data into every row with no error at all.
 */
class BqsHeaderMap
{
    /**
     * Row-1 band labels whose columns are data rather than schema.
     */
    public const string BAND_IN_DC_UNITS = 'in dc units';

    /**
     * The static `{band}_{leaf}` header pairs, mapped to their `bqs_rows` column.
     *
     * The key is the normalised band and leaf joined by `|`, with an empty band for
     * the 33 ungrouped columns in A–AG. Normalisation is {@see self::normalise()}.
     *
     * Two entries are worth reading twice:
     *
     * - `initial set units per store|extra initial packs` — the band and the leaf
     *   disagree in the source file (`AL1` says one thing, `AL2` another). The leaf
     *   wins, because it is what the values are.
     * - `|buyer` — column D is a *person*, the buyer's own merchant. It is never the
     *   `buyers` foreign key, which lives on the parent sheet.
     *
     * @var array<string, string>
     */
    public const array STATIC_COLUMNS = [
        // A–AG — the 33 ungrouped leaf columns.
        '|fye' => 'fye',
        '|season' => 'season',
        '|department' => 'department',
        '|buyer' => 'buyer_merchant',
        '|item status' => 'item_status',
        '|quote id' => 'quote_id',
        '|category' => 'category',
        '|sub category' => 'sub_category',
        '|brand id' => 'brand_id',
        '|fine line' => 'fine_line',
        '|vendor style #' => 'vendor_style_no',
        '|item description' => 'item_description',
        '|pantone colour' => 'pantone_colour',
        '|colour family' => 'colour_family',
        '|color variant' => 'colour_variant',
        '|other colour' => 'other_colour',
        '|first cost $' => 'first_cost',
        '|regular cost $' => 'regular_cost',
        '|regular retail $' => 'regular_retail',
        '|regular imu%' => 'regular_imu_pct',
        '|wm wk in store' => 'wm_wk_in_store',
        '|reg #wos' => 'reg_wos',
        '|season code' => 'season_code',
        '|on floor calendar month' => 'on_floor_month',
        '|vendor name' => 'vendor_name',
        '|vendor nbr' => 'vendor_no',
        '|imp/dom' => 'imp_dom',
        '|country of origin' => 'country_of_origin',
        '|factory id' => 'factory_id',
        '|factory name' => 'factory_name',
        '|initial po type' => 'initial_po_type',
        '|replen po type' => 'replen_po_type',
        '|reg ecom penetration percent' => 'reg_ecom_penetration_pct',

        // AH — Number of stores.
        'number of stores|total stores' => 'total_stores',

        // AI–AK — Initial Set Units.
        'initial set units|store' => 'initial_set_units_store',
        'initial set units|ecomm' => 'initial_set_units_ecomm',
        'initial set units|omni' => 'initial_set_units_omni',

        // AL — the band and the leaf disagree; the leaf wins.
        'initial set units per store|extra initial packs' => 'extra_initial_packs',

        // AM–AO — Total BUY Units.
        'total buy units|store' => 'total_buy_units_store',
        'total buy units|ecomm' => 'total_buy_units_ecomm',
        'total buy units|omni' => 'total_buy_units_omni',

        // AP–AR — Replenishment Units.
        'replenishment units|store' => 'replenishment_units_store',
        'replenishment units|ecomm' => 'replenishment_units_ecomm',
        'replenishment units|omni' => 'replenishment_units_omni',

        // AS–AU — First Cost.
        'first cost|store' => 'first_cost_store',
        'first cost|ecomm' => 'first_cost_ecomm',
        'first cost|omni' => 'first_cost_omni',

        // AV–AX — Landed Store Cost.
        'landed store cost|store' => 'landed_store_cost_store',
        'landed store cost|ecomm' => 'landed_store_cost_ecomm',
        'landed store cost|omni' => 'landed_store_cost_omni',

        // AY–BA — Total Buy Dollar.
        'total buy dollar|store' => 'total_buy_dollar_store',
        'total buy dollar|ecomm' => 'total_buy_dollar_ecomm',
        'total buy dollar|omni' => 'total_buy_dollar_omni',

        // BB–BI — Pack Details.
        'pack details|commodity type' => 'commodity_type',
        'pack details|fixture capacity' => 'fixture_capacity',
        'pack details|pack ratio' => 'pack_ratio',
        'pack details|pack units' => 'pack_units',
        'pack details|replen type' => 'replen_type',
        'pack details|replen pack' => 'replen_pack',
        'pack details|vndr pack' => 'vndr_pack',
        'pack details|whse pack' => 'whse_pack',
    ];

    /**
     * The columns without which a workbook is not a BQS.
     *
     * Deliberately short. It is every component of the row key
     * ({@see BqsRowKey}) plus the one quantity that makes a BQS a buy plan — the
     * minimum needed to identify a row and know what was bought. A missing member
     * refuses the file by name; anything else absent is a warning, because George
     * trimming a column the application does not key on is not a reason to stop
     * importing.
     *
     * @var list<string>
     */
    public const array REQUIRED_COLUMNS = [
        'fye',
        'season',
        'department',
        'vendor_style_no',
        'pantone_colour',
        'colour_variant',
        'item_description',
        'total_buy_units_store',
    ];

    /**
     * Normalise a header cell for matching.
     *
     * Lowercased, with every run of whitespace — including the non-breaking spaces
     * Excel leaves behind — collapsed to one, and the result trimmed. The source
     * file's `AL1` really does end in a trailing space, so this is not hypothetical.
     */
    public static function normalise(?string $value): string
    {
        $value = str_replace(["\u{00A0}", "\r", "\n", "\t"], ' ', (string) $value);

        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? ''));
    }

    /**
     * Work out which band each column belongs to, from the sheet's merge ranges.
     *
     * A row-1 band **is** a merged cell — `AI1:AK1` really is one cell spanning Store,
     * Ecomm and OMNI — and PhpSpreadsheet returns its label only in the top-left. So
     * the merge range is the band's exact extent, and a band cell in no merge covers
     * its own column alone (which `AH1` and `AL1` in the reference file both do).
     *
     * **This replaced a carry-forward heuristic**, which took the last non-empty label
     * and repeated it rightwards until the next one. That is right in the middle of a
     * band and wrong at the end of the last one: any column added to the right of
     * `In DC Units` inherited that band and was read as a malformed month instead of
     * being reported as an unrecognised column. The merge ranges say where a band
     * stops; guessing does not.
     *
     * @param  list<string|null>  $bandRow  the band row's own cell values, column-ordered
     * @param  list<string>  $mergeRanges  every merge on the sheet, e.g. `AI1:AK1`
     * @return list<string>
     */
    public static function resolveBands(array $bandRow, array $mergeRanges, int $bandRowIndex): array
    {
        $bands = array_map(self::normalise(...), $bandRow);

        foreach ($mergeRanges as $range) {
            if (! preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/i', $range, $matches)) {
                continue;
            }

            [, $firstColumn, $firstRow, $lastColumn, $lastRow] = $matches;

            if ((int) $firstRow !== $bandRowIndex || (int) $lastRow !== $bandRowIndex) {
                continue;
            }

            $from = Coordinate::columnIndexFromString($firstColumn);
            $to = Coordinate::columnIndexFromString($lastColumn);
            $label = $bands[$from - 1] ?? '';

            for ($column = $from; $column <= $to; $column++) {
                if (array_key_exists($column - 1, $bands)) {
                    $bands[$column - 1] = $label;
                }
            }
        }

        return $bands;
    }

    /**
     * The pack band a row-1 label denotes, or `null` if it is not a pack band.
     */
    public static function packTypeFor(string $band): ?BqsPackType
    {
        foreach (BqsPackType::cases() as $type) {
            if ($band === self::normalise($type->bandLabel())) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Whether a row-1 label denotes the monthly DC-intake band.
     */
    public static function isMonthBand(string $band): bool
    {
        return $band === self::BAND_IN_DC_UNITS;
    }

    /**
     * A stable fingerprint of the static half of a header.
     *
     * Only the mapped `{band}_{leaf}` field names go in, sorted — so reordering the
     * columns does not change it, but adding, removing or renaming one does. The
     * dynamic bands are excluded by construction: a new season would otherwise look
     * like a template change on every single upload.
     *
     * @param  array<int, string>  $mappedFields
     */
    public static function fingerprint(array $mappedFields): string
    {
        $fields = array_values($mappedFields);
        sort($fields);

        return substr(hash('sha256', implode('|', $fields)), 0, 12);
    }
}
