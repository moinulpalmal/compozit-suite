<?php

namespace App\Services\Merchandising\PoParser\Support;

use App\Services\Merchandising\PoParser\Validators\PoDataValidator;

/**
 * Pulls one captured group out of a block of text, or null when the label is absent.
 *
 * Every field extractor is dozens of "match this label, keep group 1" steps, and
 * writing each as an `if (preg_match(...))` guard buries the *patterns* — which are
 * the only part worth reading — under identical scaffolding.
 *
 * **Absence is null, never an exception.** A Walmart purchase order leaves whole
 * blocks blank as a matter of course, so a missing label is ordinary data. Whether a
 * particular absence matters is
 * {@see PoDataValidator}'s decision,
 * made once, rather than fifteen extractors each having an opinion.
 */
final class Capture
{
    /**
     * The first captured group, trimmed, or null when the pattern does not match.
     */
    public static function text(string $pattern, string $text): ?string
    {
        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        $value = trim($matches[1] ?? '');

        return $value === '' ? null : $value;
    }

    /**
     * The first captured group as a float, or null when the pattern does not match.
     */
    public static function float(string $pattern, string $text): ?float
    {
        $value = self::text($pattern, $text);

        return $value === null ? null : NumberParser::parse($value);
    }

    /**
     * The first captured group as an integer, or null when the pattern does not match.
     */
    public static function int(string $pattern, string $text): ?int
    {
        $value = self::text($pattern, $text);

        return $value === null ? null : NumberParser::parseInt($value);
    }

    /**
     * The first captured group as an ISO date, or null.
     */
    public static function date(string $pattern, string $text): ?string
    {
        return DateParser::parse(self::text($pattern, $text));
    }

    /**
     * The first captured group as an ISO timestamp, or null.
     */
    public static function dateTime(string $pattern, string $text): ?string
    {
        return DateParser::parseDateTime(self::text($pattern, $text));
    }

    /**
     * Whether the pattern appears at all — for the fixed phrases that act as flags.
     */
    public static function flag(string $pattern, string $text): bool
    {
        return preg_match($pattern, $text) === 1;
    }

    /**
     * Join a segment's lines back into the text block the patterns match against.
     *
     * @param  list<array{index: int, text: string}>  $lines
     */
    public static function joinLines(array $lines): string
    {
        return implode("\n", array_column($lines, 'text'));
    }
}
