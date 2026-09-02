<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * One pack on a purchase order: its identifiers, its cost stack, its physical
 * attributes, and the colour/size lines inside it.
 *
 * A pack is the unit Walmart orders and cartons are counted in; the
 * {@see LineItemDto}s beneath it are the unit quantity is stated in. `$costs` and
 * `$physical` stay associative arrays rather than becoming typed objects because
 * their key sets are open — Walmart prints whichever cost rows apply to that pack,
 * and a fixed shape would drop the ones this template has not shown us yet.
 */
final readonly class PackDto
{
    /**
     * @param  array<string, mixed>  $costs
     * @param  array<string, mixed>  $physical
     * @param  list<LineItemDto>  $lineItems
     */
    public function __construct(
        public ?int $packNumber = null,
        public ?string $packDescription = null,
        public ?string $subclassFineline = null,
        public ?string $oldQsNumber = null,
        public ?string $caseUpc = null,
        public ?string $productDesc1 = null,
        public ?string $assortmentId = null,
        public ?string $color = null,
        public ?string $vendorStock = null,
        public array $costs = [],
        public array $physical = [],
        public ?LineItemHeaderDto $lineItemHeader = null,
        public array $lineItems = [],
        public ?PackCommentsDto $comments = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pack_number' => $this->packNumber,
            'pack_description' => $this->packDescription,
            'subclass_fineline' => $this->subclassFineline,
            'old_qs_number' => $this->oldQsNumber,
            'case_upc' => $this->caseUpc,
            'product_desc1' => $this->productDesc1,
            'assortment_id' => $this->assortmentId,
            'color' => $this->color,
            'vendor_stock' => $this->vendorStock,
            'costs' => $this->costs,
            'physical' => $this->physical,
            'line_item_header' => $this->lineItemHeader?->toArray(),
            'line_items' => array_map(
                static fn (LineItemDto $item): array => $item->toArray(),
                $this->lineItems,
            ),
            'comments' => $this->comments?->toArray(),
        ];
    }
}
