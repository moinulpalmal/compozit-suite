<?php

namespace App\Enums\Merchandising;

/**
 * How badly a parser warning reflects on the document it came from.
 *
 * Severity is what turns a list of warnings into a single
 * {@see PoParseStatus}: any {@see PoWarningSeverity::Error} fails the parse
 * outright, and everything else erodes a confidence score that is compared against
 * `config('po-parser.parsing.warn_threshold')`.
 *
 * **This is the parser's vocabulary, not the toast's.** The four toast types in
 * ARCHITECTURE.md §8.8 describe what the *application* did about a request; these
 * three describe what the *document* looked like.
 */
enum PoWarningSeverity: string
{
    /** A field that must be present is missing or malformed. The parse has failed. */
    case Error = 'error';

    /** Something disagrees with an expectation — a total that does not add up. */
    case Warning = 'warning';

    /** Worth recording, not worth acting on. */
    case Info = 'info';

    /**
     * How much one warning of this severity removes from a parse's confidence.
     *
     * The weights live here rather than in the service so that adding a severity
     * cannot leave a `match` somewhere else silently unhandled.
     */
    public function confidencePenalty(): float
    {
        return match ($this) {
            self::Error => 0.2,
            self::Warning => 0.05,
            self::Info => 0.01,
        };
    }

    /**
     * The label rendered beside a warning.
     */
    public function label(): string
    {
        return match ($this) {
            self::Error => __('Error'),
            self::Warning => __('Warning'),
            self::Info => __('Info'),
        };
    }
}
