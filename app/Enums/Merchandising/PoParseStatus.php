<?php

namespace App\Enums\Merchandising;

use App\Services\Merchandising\PoParser\ParserService;

/**
 * How much the application trusts a parsed purchase order.
 *
 * Every parsed PO is persisted regardless of this value — see
 * [`documentation/merchandising.md`](../../../documentation/merchandising.md), which
 * records why, and what a downstream reader must therefore do. A `Failed` row is
 * stored so its warnings stay inspectable next to the document that produced them;
 * it is **not** trustworthy order data.
 *
 * The thresholds live in `config('po-parser.parsing.warn_threshold')` and are
 * applied by {@see ParserService}.
 */
enum PoParseStatus: string
{
    /** Every validation rule passed. */
    case Success = 'success';

    /** Parsed, but confidence fell below the threshold — a human should look. */
    case NeedsReview = 'needs_review';

    /** At least one error-severity warning. The data is known to be wrong. */
    case Failed = 'failed';

    /**
     * The label rendered in a status cell.
     */
    public function label(): string
    {
        return match ($this) {
            self::Success => __('Success'),
            self::NeedsReview => __('Needs review'),
            self::Failed => __('Failed'),
        };
    }

    /**
     * Whether this status marks data a downstream module may rely on.
     */
    public function isUsable(): bool
    {
        return $this !== self::Failed;
    }

    /**
     * Every case as the option list a filter cell renders.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
