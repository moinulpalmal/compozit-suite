<?php

namespace App\Services\Merchandising\PoParser\Support;

use App\DataTransferObjects\Merchandising\Po\WarningDto;
use App\Enums\Merchandising\PoParseStatus;
use App\Enums\Merchandising\PoWarningSeverity;

/**
 * Turns a list of warnings into a confidence score and a status.
 *
 * Used twice over different scopes, which is the reason it is its own class: the
 * parser grades a whole *file* for the import record, and the import service grades
 * each *purchase order* for its own row. Both must answer the same way, and a second
 * copy of the arithmetic is how they would stop doing so.
 */
final class ParseGrader
{
    /**
     * Confidence starts at 1.0; each warning erodes it by its severity's weight.
     *
     * @param  list<WarningDto>  $warnings
     */
    public function confidence(array $warnings): float
    {
        $score = 1.0;

        foreach ($warnings as $warning) {
            $score -= $warning->severity->confidencePenalty();
        }

        return max(0.0, round($score, 3));
    }

    /**
     * Any error fails outright; otherwise the threshold decides.
     *
     * @param  list<WarningDto>  $warnings
     */
    public function status(array $warnings): PoParseStatus
    {
        foreach ($warnings as $warning) {
            if ($warning->severity === PoWarningSeverity::Error) {
                return PoParseStatus::Failed;
            }
        }

        return $this->confidence($warnings) < (float) config('po-parser.parsing.warn_threshold')
            ? PoParseStatus::NeedsReview
            : PoParseStatus::Success;
    }
}
