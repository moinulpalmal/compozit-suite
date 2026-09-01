<?php

namespace App\Services\Merchandising;

use App\DataTransferObjects\Merchandising\BqsRowDto;
use App\DataTransferObjects\Merchandising\BqsWorkbookDto;
use App\Enums\Merchandising\BqsPackType;
use App\Exceptions\Merchandising\BqsImportException;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads a BQS workbook into {@see BqsWorkbookDto}. The only class that touches
 * PhpSpreadsheet.
 *
 * ## The two header rows
 *
 * Row 1 is merged group bands, row 2 is leaf headers, data starts at row 3. The header
 * rows are **found** rather than assumed: {@see self::locateHeader()} scans the first
 * few rows for the one containing `FYE`, so a workbook with a title row above the
 * header still reads. Positional reading of the columns themselves was rejected in
 * planning — an inserted column would shift 89 fields and corrupt every row silently.
 *
 * ## Three bands are data, not schema
 *
 * `In DC Units` is headed with month names and `Break Packs` / `Case Packs` with size
 * labels. Both change with every season and size range, so their columns are read as
 * values and become rows in `bqs_row_months` and `bqs_row_pack_sizes`.
 *
 * ## Fidelity over correction
 *
 * Where the buyer's own arithmetic disagrees with itself — OMNI ≠ Store + Ecomm, or
 * Total BUY ≠ Initial + Replen — the values are stored **exactly as sent** and a
 * warning is raised. The workbook is the source of truth; silently recomputing it
 * would make the application disagree with the document it claims to hold, and nobody
 * would find out until a costing did.
 */
class BqsWorkbookReader
{
    /** How many rows to scan for the leaf header before giving up. */
    private const int HEADER_SEARCH_LIMIT = 10;

    /** The leaf header that identifies the header row. */
    private const string HEADER_ANCHOR = 'fye';

    /** @var list<array{severity: string, message: string, line: int|null}> */
    private array $warnings = [];

    /**
     * Read a workbook from disk.
     *
     * @throws BqsImportException when the file holds nothing recognisable as a BQS
     */
    public function read(string $path, string $originalName, int $maxRows): BqsWorkbookDto
    {
        $this->warnings = [];

        try {
            $reader = IOFactory::createReaderForFile($path);

            /*
             * **`setReadDataOnly(true)` cannot be used here**, tempting as it is.
             * It discards merge geometry along with the styles, and a row-1 band *is*
             * a merged cell — without the ranges, `AI1:AK1` collapses to a label on
             * `AI` alone, `Ecomm` and `OMNI` map to nothing, and seventeen of the
             * eighteen month columns disappear. That failure is silent: the import
             * succeeds with most of the workbook missing.
             *
             * The cost is loading formatting for a sheet bounded by
             * `bqs-import.limits.max_rows`. If that ever bites, the fix is to read the
             * merges through a second cheap pass, not to turn this back on.
             */
            $sheet = $reader->load($path)->getSheet(0);
        } catch (Throwable) {
            throw BqsImportException::unreadable($originalName);
        }

        [$bandRowIndex, $leafRowIndex] = $this->locateHeader($sheet, $originalName);

        $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $bands = BqsHeaderMap::resolveBands(
            $this->rowValues($sheet, $bandRowIndex, $lastColumn),
            array_keys($sheet->getMergeCells()),
            $bandRowIndex,
        );
        $leaves = $this->rowValues($sheet, $leafRowIndex, $lastColumn);

        $plan = $this->planColumns($bands, $leaves);

        $missing = array_values(array_diff(BqsHeaderMap::REQUIRED_COLUMNS, array_values($plan['static'])));

        if ($missing !== []) {
            throw BqsImportException::missingColumns($missing);
        }

        $rows = $this->readRows($sheet, $leafRowIndex + 1, $plan, $maxRows, $originalName);

        return new BqsWorkbookDto(
            sheetName: $sheet->getTitle(),
            headerFingerprint: BqsHeaderMap::fingerprint($plan['static']),
            rows: $rows,
            mappedColumns: $plan['static'],
            unmappedColumns: $plan['unmapped'],
            warnings: $this->warnings,
        );
    }

    /**
     * Find the band row and the leaf row.
     *
     * The leaf row is the one carrying `FYE`; the band row is whatever sits directly
     * above it. A workbook whose header starts on row 1 has no band row, which is
     * handled by reading row 0 as empty — every band is then blank, and only the 33
     * ungrouped columns map. That is a legitimate BQS export, not an error.
     *
     * @return array{int, int}
     *
     * @throws BqsImportException
     */
    private function locateHeader(Worksheet $sheet, string $originalName): array
    {
        $limit = min(self::HEADER_SEARCH_LIMIT, $sheet->getHighestDataRow());

        for ($row = 1; $row <= $limit; $row++) {
            foreach ($this->rowValues($sheet, $row, 40) as $value) {
                if (BqsHeaderMap::normalise($value) === self::HEADER_ANCHOR) {
                    return [$row - 1, $row];
                }
            }
        }

        throw BqsImportException::unreadable($originalName);
    }

    /**
     * Decide what every column is, once, before any row is read.
     *
     * @param  list<string>  $bands
     * @param  list<string|null>  $leaves
     * @return array{
     *     static: array<string, string>,
     *     months: array<string, array{label: string, month: string}>,
     *     packs: array<string, array{type: BqsPackType, label: string, order: int}>,
     *     unmapped: list<string>
     * }
     */
    private function planColumns(array $bands, array $leaves): array
    {
        $static = [];
        $months = [];
        $packs = [];
        $unmapped = [];
        $packOrder = [];

        foreach ($leaves as $index => $leaf) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $band = $bands[$index] ?? '';
            $leafText = BqsHeaderMap::normalise($leaf);

            if ($leafText === '') {
                continue;
            }

            if (BqsHeaderMap::isMonthBand($band)) {
                $month = $this->parseMonth((string) $leaf);

                if ($month === null) {
                    $this->warn("Ignored the 'In DC Units' column headed \"{$leaf}\" — it is not a month.");

                    continue;
                }

                $months[$letter] = ['label' => trim((string) $leaf), 'month' => $month];

                continue;
            }

            $packType = BqsHeaderMap::packTypeFor($band);

            if ($packType instanceof BqsPackType) {
                $key = $packType->value;
                $packOrder[$key] = ($packOrder[$key] ?? 0) + 1;
                $packs[$letter] = [
                    'type' => $packType,
                    'label' => trim((string) $leaf),
                    'order' => $packOrder[$key],
                ];

                continue;
            }

            $field = BqsHeaderMap::STATIC_COLUMNS[$band.'|'.$leafText] ?? null;

            if ($field === null) {
                $unmapped[] = $band === '' ? (string) $leaf : "{$band} / {$leaf}";

                continue;
            }

            $static[$letter] = $field;
        }

        if ($unmapped !== []) {
            $this->warn(sprintf(
                'The workbook has %d column(s) this application does not recognise, and they were not imported: %s.',
                count($unmapped),
                implode(', ', $unmapped),
            ));
        }

        return ['static' => $static, 'months' => $months, 'packs' => $packs, 'unmapped' => $unmapped];
    }

    /**
     * Read every data row beneath the header.
     *
     * @param  array{static: array<string, string>, months: array<string, array{label: string, month: string}>, packs: array<string, array{type: BqsPackType, label: string, order: int}>, unmapped: list<string>}  $plan
     * @return list<BqsRowDto>
     *
     * @throws BqsImportException
     */
    private function readRows(Worksheet $sheet, int $firstRow, array $plan, int $maxRows, string $originalName): array
    {
        $rows = [];
        $lastRow = $sheet->getHighestDataRow();

        for ($line = $firstRow; $line <= $lastRow; $line++) {
            $values = [];

            foreach ($plan['static'] as $letter => $field) {
                $values[$field] = $this->cell($sheet, $letter, $line);
            }

            /* A row with no style and no colour is the blank tail Excel leaves. */
            if ($this->isBlank($values)) {
                continue;
            }

            $values = $this->castValues($values);
            $this->checkArithmetic($values, $line);

            $rows[] = new BqsRowDto(
                lineNo: $line,
                values: $values,
                months: $this->readMonths($sheet, $plan['months'], $line),
                packSizes: $this->readPackSizes($sheet, $plan['packs'], $line),
            );

            if (count($rows) > $maxRows) {
                throw BqsImportException::tooManyRows(count($rows), $maxRows);
            }
        }

        if ($rows === []) {
            throw BqsImportException::unreadable($originalName);
        }

        return $rows;
    }

    /**
     * @param  array<string, array{label: string, month: string}>  $columns
     * @return list<array{month: string, month_label: string, dc_units: int|null}>
     */
    private function readMonths(Worksheet $sheet, array $columns, int $line): array
    {
        $months = [];

        foreach ($columns as $letter => $column) {
            $months[] = [
                'month' => $column['month'],
                'month_label' => $column['label'],
                'dc_units' => $this->toInt($this->cell($sheet, $letter, $line)),
            ];
        }

        return $months;
    }

    /**
     * @param  array<string, array{type: BqsPackType, label: string, order: int}>  $columns
     * @return list<array{pack_type: BqsPackType, size_label: string, size_order: int, quantity: int|null}>
     */
    private function readPackSizes(Worksheet $sheet, array $columns, int $line): array
    {
        $packs = [];

        foreach ($columns as $letter => $column) {
            $packs[] = [
                'pack_type' => $column['type'],
                'size_label' => $column['label'],
                'size_order' => $column['order'],
                'quantity' => $this->toInt($this->cell($sheet, $letter, $line)),
            ];
        }

        return $packs;
    }

    /**
     * Turn raw cell values into what the columns expect.
     *
     * Numbers are left as strings for the decimal columns — casting to float here is
     * how `70711.199999999997` becomes a rounding error in the database rather than in
     * Excel. Integer columns are cast because they are counts.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, scalar|null>
     */
    private function castValues(array $values): array
    {
        foreach (self::INTEGER_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = $this->toInt($values[$field]);
            }
        }

        foreach ($values as $field => $value) {
            if (is_string($value)) {
                $values[$field] = trim($value) === '' ? null : trim($value);
            }
        }

        /* `"3 (2027-02-13)"` — kept whole, and split for use. */
        if (isset($values['wm_wk_in_store']) && is_string($values['wm_wk_in_store'])) {
            [$week, $date] = $this->splitWeekInStore($values['wm_wk_in_store']);
            $values['wm_wk_in_store_week'] = $week;
            $values['wm_wk_in_store_date'] = $date;
        }

        return $values;
    }

    /**
     * Split `"3 (2027-02-13)"` into its week number and its date.
     *
     * Either half may be absent; a cell holding only `50` yields a week and no date.
     *
     * @return array{int|null, string|null}
     */
    private function splitWeekInStore(string $raw): array
    {
        preg_match('/^\s*(\d+)?\s*(?:\(\s*([\d\-\/]+)\s*\))?\s*$/', $raw, $matches);

        $week = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : null;
        $date = null;

        if (isset($matches[2]) && $matches[2] !== '') {
            try {
                $date = Carbon::parse($matches[2])->toDateString();
            } catch (InvalidFormatException) {
                $date = null;
            }
        }

        return [$week, $date];
    }

    /**
     * Warn where the buyer's own totals disagree with their own parts.
     *
     * Never corrected — see the class docblock. Every row of every file seen so far
     * satisfies both identities, so a failure here is genuinely worth a look.
     *
     * @param  array<string, scalar|null>  $values
     */
    private function checkArithmetic(array $values, int $line): void
    {
        $identities = [
            ['initial_set_units_omni', ['initial_set_units_store', 'initial_set_units_ecomm'], 'Initial Set Units OMNI'],
            ['total_buy_units_omni', ['total_buy_units_store', 'total_buy_units_ecomm'], 'Total BUY Units OMNI'],
            ['replenishment_units_omni', ['replenishment_units_store', 'replenishment_units_ecomm'], 'Replenishment Units OMNI'],
        ];

        foreach ($identities as [$total, $parts, $label]) {
            if (! is_int($values[$total] ?? null)) {
                continue;
            }

            $sum = 0;

            foreach ($parts as $part) {
                if (! is_int($values[$part] ?? null)) {
                    continue 2;
                }

                $sum += (int) $values[$part];
            }

            if ($sum !== $values[$total]) {
                $this->warn(
                    "{$label} is {$values[$total]} but Store + Ecomm is {$sum}. The workbook's own values were stored unchanged.",
                    $line,
                );
            }
        }
    }

    /**
     * Parse `November-2026` into the first of that month.
     */
    private function parseMonth(string $header): ?string
    {
        $header = trim(str_replace(["\u{00A0}", '_', '/'], [' ', '-', '-'], $header));

        try {
            return Carbon::createFromFormat('!F-Y', $header)?->toDateString()
                ?? Carbon::parse($header)->startOfMonth()->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function isBlank(array $values): bool
    {
        foreach (BqsRowKey::COMPONENTS as $component) {
            $value = $values[$component] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cell(Worksheet $sheet, string $letter, int $line): mixed
    {
        return $sheet->getCell($letter.$line)->getValue();
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) round((float) $value);
    }

    /**
     * @return list<string|null>
     */
    private function rowValues(Worksheet $sheet, int $row, int $lastColumn): array
    {
        if ($row < 1) {
            return array_fill(0, $lastColumn, null);
        }

        $values = [];

        for ($column = 1; $column <= $lastColumn; $column++) {
            $value = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$row)->getValue();
            $values[] = is_scalar($value) ? (string) $value : null;
        }

        return $values;
    }

    private function warn(string $message, ?int $line = null): void
    {
        $this->warnings[] = ['severity' => 'warning', 'message' => $message, 'line' => $line];
    }

    /**
     * Columns that hold counts and are cast to `int`.
     *
     * Money is deliberately absent: it stays a string all the way to the `decimal`
     * columns, so Excel's `70711.199999999997` is truncated by the database's own
     * scale rather than by a float cast.
     *
     * @var list<string>
     */
    private const array INTEGER_FIELDS = [
        'total_stores',
        'initial_set_units_store', 'initial_set_units_ecomm', 'initial_set_units_omni',
        'extra_initial_packs',
        'total_buy_units_store', 'total_buy_units_ecomm', 'total_buy_units_omni',
        'replenishment_units_store', 'replenishment_units_ecomm', 'replenishment_units_omni',
        'fixture_capacity', 'pack_units', 'vndr_pack', 'whse_pack',
    ];
}
