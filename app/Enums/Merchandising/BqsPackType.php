<?php

namespace App\Enums\Merchandising;

use App\Models\Merchandising\BqsRowPackSize;

/**
 * Which of a BQS row's two pack bands a size quantity belongs to.
 *
 * The source workbook carries the same five size labels twice over, under two merged
 * row-1 bands — `Break Packs` and `Case Packs`. Since both become rows in
 * {@see BqsRowPackSize} rather than columns, something has to say which band a size
 * came from, and this is it.
 *
 * `Case` is a reserved word in PHP but not a reserved *enum case name*; it is spelled
 * here exactly as the buyer's band is, per ARCHITECTURE.md §6.1's TitleCase rule.
 */
enum BqsPackType: string
{
    /** The `Break Packs` band — how a case is broken down for a store. */
    case Break = 'break';

    /** The `Case Packs` band — the size ratio inside a shipped case. */
    case Case = 'case';

    /**
     * The row-1 band label this case is read from, matched case-insensitively.
     */
    public function bandLabel(): string
    {
        return match ($this) {
            self::Break => 'Break Packs',
            self::Case => 'Case Packs',
        };
    }

    /**
     * The label rendered above a pack column on the BQS detail page.
     */
    public function label(): string
    {
        return match ($this) {
            self::Break => __('Break pack'),
            self::Case => __('Case pack'),
        };
    }
}
