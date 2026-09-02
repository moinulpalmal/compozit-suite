<?php

namespace App\DataTransferObjects\Merchandising;

use App\Enums\Merchandising\BqsParseStatus;
use App\Services\Merchandising\BqsHeaderMap;
use App\Services\Merchandising\BqsWorkbookReader;

/**
 * Everything {@see BqsWorkbookReader} got out of one uploaded workbook.
 *
 * Crosses the boundary between reading a file and deciding what to do with it, in a
 * shape no model has — ARCHITECTURE.md §6.7. The service that receives it does not
 * touch PhpSpreadsheet at all, which is what keeps the reader replaceable.
 *
 * `warnings` is the part that survives longest: it is written to
 * `bqs_imports.payload` so an unmapped column or a row whose own arithmetic disagrees
 * stays next to the workbook that produced it, rather than being reported once in a
 * toast and lost.
 */
final readonly class BqsWorkbookDto
{
    /**
     * @param  list<BqsRowDto>  $rows
     * @param  array<string, string>  $mappedColumns  column letter → `bqs_rows` column
     * @param  list<string>  $unmappedColumns  band+leaf headers nothing claimed
     * @param  list<array{severity: string, message: string, line: int|null}>  $warnings
     */
    public function __construct(
        public string $sheetName,
        public string $headerFingerprint,
        public array $rows,
        public array $mappedColumns,
        public array $unmappedColumns,
        public array $warnings,
    ) {}

    /**
     * How much the application trusts this workbook.
     *
     * A workbook that could not be read never gets this far — the reader throws
     * instead, because there is nothing to store. So the only question here is whether
     * anything wants a human's eye.
     */
    public function status(): BqsParseStatus
    {
        return $this->warnings === [] ? BqsParseStatus::Success : BqsParseStatus::NeedsReview;
    }

    /**
     * The row keys this workbook claims, for collision detection.
     *
     * @return list<string>
     */
    public function rowKeys(): array
    {
        return array_map(static fn (BqsRowDto $row): string => $row->key(), $this->rows);
    }

    /**
     * The three header facts every row in a workbook shares, promoted to the sheet.
     *
     * Read from the first row rather than validated across all of them: a workbook
     * mixing seasons is not something George sends, and refusing one on that basis
     * would be inventing a rule. If it ever happens, the rows still carry their own.
     *
     * @return array{fye: string|null, season: string|null, department: string|null}
     */
    public function sheetHeader(): array
    {
        $first = $this->rows[0] ?? null;

        return [
            'fye' => $this->stringOrNull($first?->values['fye'] ?? null),
            'season' => $this->stringOrNull($first?->values['season'] ?? null),
            'department' => $this->stringOrNull($first?->values['department'] ?? null),
        ];
    }

    /**
     * What is written to `bqs_imports.payload`.
     *
     * The resolved header map goes in as well as the warnings: when George changes the
     * template, the question asked afterwards is always "what did it map to *last*
     * time", and {@see BqsHeaderMap} only knows what it maps to now.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sheet_name' => $this->sheetName,
            'header_fingerprint' => $this->headerFingerprint,
            'mapped_columns' => $this->mappedColumns,
            'unmapped_columns' => $this->unmappedColumns,
            'warnings' => $this->warnings,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
