<?php

namespace App\Exceptions\Merchandising\PoParser;

use App\Enums\Merchandising\PoParseStatus;

/**
 * The file was readable, but it is not a document this parser can accept —
 * it contains no Walmart purchase-order pages, or it exceeds a configured limit.
 *
 * This is distinct from a low-confidence parse: that produces warnings and a
 * {@see PoParseStatus} value, not an exception.
 */
class PoValidationException extends PoParserException {}
