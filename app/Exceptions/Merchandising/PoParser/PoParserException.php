<?php

namespace App\Exceptions\Merchandising\PoParser;

use Exception;

/**
 * Base class for every failure the purchase-order parser raises.
 *
 * Catch this to handle "the document could not be turned into data" without
 * caring which stage gave up. The import controller does exactly that, and
 * reports it through the shared toast rather than a stack trace.
 */
class PoParserException extends Exception {}
