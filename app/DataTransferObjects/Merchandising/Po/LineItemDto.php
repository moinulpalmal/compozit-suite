<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * One colour/size line of a pack — the level at which quantity is ordered.
 *
 * **This is the only part of the document that becomes rows rather than JSON.**
 * Consumption is computed from quantity × colour × size, and ARCHITECTURE.md §5
 * makes Merchandising the upstream source of that for Production, so these have
 * to be joinable. Everything else about a purchase order lives in the payload
 * column. See [`documentation/merchandising.md`](../../../../documentation/merchandising.md).
 *
 * Identifiers are strings, not integers: a 13-digit UPC exceeds nothing on 64-bit
 * PHP but is an identifier rather than a quantity, and leading zeros are
 * significant.
 */
final readonly class LineItemDto
{
    public function __construct(
        public ?string $color = null,
        public ?string $size = null,
        public ?int $quantity = null,
        public ?string $itemNumber = null,
        public ?string $vendorStockNumber = null,
        public ?string $mfgStockNumber = null,
        public ?string $itemDescription1 = null,
        public ?string $itemDescription2 = null,
        public ?string $upcDescription = null,
        public ?string $signingDescription = null,
        public ?string $ticketDescription = null,
        public ?float $uomQty = null,
        public ?string $uomCode = null,
        public ?string $productNumber = null,
        public ?string $upcNumber = null,
    ) {}

    /**
     * @return array<string, string|int|float|null>
     */
    public function toArray(): array
    {
        return [
            'color' => $this->color,
            'size' => $this->size,
            'quantity' => $this->quantity,
            'item_number' => $this->itemNumber,
            'vendor_stock_number' => $this->vendorStockNumber,
            'mfg_stock_number' => $this->mfgStockNumber,
            'item_description1' => $this->itemDescription1,
            'item_description2' => $this->itemDescription2,
            'upc_description' => $this->upcDescription,
            'signing_description' => $this->signingDescription,
            'ticket_description' => $this->ticketDescription,
            'uom_qty' => $this->uomQty,
            'uom_code' => $this->uomCode,
            'product_number' => $this->productNumber,
            'upc_number' => $this->upcNumber,
        ];
    }
}
