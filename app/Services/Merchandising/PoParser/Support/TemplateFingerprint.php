<?php

namespace App\Services\Merchandising\PoParser\Support;

use App\Enums\Merchandising\ParserState;

/**
 * A short, stable hash of the *shape* of a parsed document.
 *
 * It is computed from the ordered set of sections the state machine recognised,
 * not from any of the values inside them — so every purchase order printed from
 * the same Walmart template fingerprints identically, whatever it says.
 *
 * The point is drift detection. When Walmart changes the template, the extractors
 * keep running and quietly return less; a fingerprint that has never been seen
 * before is the signal that the *document* changed rather than the data. It is
 * stored on each imported purchase order for exactly that comparison.
 */
final class TemplateFingerprint
{
    /**
     * Fingerprint the distinct sections found, in the order they first appeared.
     *
     * @param  list<array{state: ParserState, lines: list<array{index: int, text: string}>}>  $segments
     */
    public static function compute(array $segments): string
    {
        $states = array_map(
            static fn (array $segment): string => $segment['state']->value,
            $segments,
        );

        return substr(sha1(implode('|', array_values(array_unique($states)))), 0, 12);
    }
}
