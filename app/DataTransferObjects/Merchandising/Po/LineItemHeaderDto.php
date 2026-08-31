<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The block above a pack's colour/size lines: dimensions, carton count, quoted cost,
 * assortment and vendor stock.
 *
 * `total_cartons_per_line` is read by validation rule V3, which compares the sum
 * across packs against the order's own master-carton total.
 *
 * An open field bag — see {@see FieldBagDto}.
 */
final readonly class LineItemHeaderDto extends FieldBagDto {}
