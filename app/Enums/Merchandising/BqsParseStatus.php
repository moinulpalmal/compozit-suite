<?php

namespace App\Enums\Merchandising;

use App\Services\Merchandising\BqsImportService;

/**
 * How much the application trusts an imported BQS.
 *
 * **This is not {@see PoParseStatus}, and the difference is real.** That enum grades a
 * *parser's* confidence against `config('po-parser.parsing.warn_threshold')` — a
 * document had to be turned into text and guessed at. A workbook is already
 * structured: either a required column was found or it was not. There is no
 * confidence score here, and no threshold, so sharing the enum would make that one's
 * docblock false.
 *
 * A `NeedsReview` BQS is stored and usable; its warnings — an unmapped column, a row
 * whose OMNI total disagrees with its own Store + Ecomm — sit on the import so they
 * stay next to the workbook that produced them. See
 * [`documentation/merchandising.md`](../../../documentation/merchandising.md).
 *
 * Applied by {@see BqsImportService}.
 */
enum BqsParseStatus: string
{
    /** Every required column mapped and every row read cleanly. */
    case Success = 'success';

    /** Read in full, but something is worth a human's attention. */
    case NeedsReview = 'needs_review';

    /**
     * The workbook could not be read as a BQS at all — nothing was stored beyond the
     * import record itself.
     */
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
