<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * One customs classification entry: tariff number, duty rates and the declarations
 * around them.
 *
 * A purchase order normally carries two — one classified by the vendor, one by
 * Walmart — and validation rule V12 warns when it does not.
 *
 * An open field bag — see {@see FieldBagDto}.
 */
final readonly class TariffDto extends FieldBagDto {}
