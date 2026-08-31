<?php

namespace App\DataTransferObjects\Merchandising\Po;

use App\Services\Merchandising\PoParser\FieldExtractors\LogisticsExtractor;

/**
 * The shipping and payment block: ports, dates, incoterm, container and payment terms.
 *
 * An open field bag — see {@see FieldBagDto} for why, and
 * {@see LogisticsExtractor}
 * for the label list it is built from.
 */
final readonly class LogisticsDto extends FieldBagDto {}
