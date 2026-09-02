<?php

namespace App\Services\Merchandising\PoParser\Support;

/**
 * Turns the numeric tokens a Walmart document prints into PHP numbers.
 *
 * Two document quirks drive this: thousands are comma-grouped (`1,234.50`), and a
 * value below one is printed with no leading zero (`.075`), which `(float)` reads
 * correctly but which is normalised here so the string form is also valid.
 */
final class NumberParser
{
    /**
     * Parse a numeric token, returning `0.0` for anything empty or absent.
     *
     * Returning zero rather than null is deliberate: every caller is summing or
     * comparing, and a null would have to be coalesced at each one.
     */
    public static function parse(?string $value): float
    {
        if ($value === null || trim($value) === '') {
            return 0.0;
        }

        $value = str_replace(',', '', trim($value));

        if (str_starts_with($value, '.')) {
            $value = '0'.$value;
        }

        return (float) $value;
    }

    /**
     * Parse a numeric token as an integer.
     */
    public static function parseInt(?string $value): int
    {
        return (int) self::parse($value);
    }
}
