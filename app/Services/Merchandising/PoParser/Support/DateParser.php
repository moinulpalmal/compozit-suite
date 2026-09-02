<?php

namespace App\Services\Merchandising\PoParser\Support;

/**
 * Reads the US-format dates a Walmart document prints and returns ISO-8601 strings.
 *
 * **The document's format is `MM/DD/YYYY`.** The fixture proves it —
 * `Preclass Approval Date: 06/26/2026` cannot be read day-first. Do not "fix" the
 * capture order to `DD/MM`: for the first twelve days of a month both readings
 * parse, so the mistake would corrupt a minority of dates and pass every test that
 * happened to use a day above twelve.
 *
 * ISO strings rather than `Carbon` instances, because these values travel into a
 * JSON payload column and out to the front end unchanged; the model casts the
 * columns it promotes.
 */
final class DateParser
{
    /**
     * Parse `MM/DD/YYYY` into `YYYY-MM-DD`, or null when there is no date to read.
     */
    public static function parse(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $value, $matches) === 1) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
        }

        return null;
    }

    /**
     * Parse `MM/DD/YYYY HH:MM:SS` into an ISO-8601 local timestamp.
     *
     * Falls back to {@see self::parse()} when the value carries no time part, so a
     * caller matching a datetime label against a date-only value still gets a date.
     */
    public static function parseDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('#(\d{2})/(\d{2})/(\d{4}) (\d{2}):(\d{2}):(\d{2})#', $value, $matches) === 1) {
            return sprintf(
                '%04d-%02d-%02dT%02d:%02d:%02d',
                $matches[3], $matches[1], $matches[2], $matches[4], $matches[5], $matches[6],
            );
        }

        return self::parse($value);
    }
}
