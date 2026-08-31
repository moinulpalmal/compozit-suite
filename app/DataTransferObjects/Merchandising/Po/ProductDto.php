<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The product the order is for, as the document's `PRODUCT:` block names it.
 */
final readonly class ProductDto
{
    public function __construct(
        public ?string $name = null,
        public ?int $productNumber = null,
        public ?string $classification = null,
    ) {}

    /**
     * @return array{name: string|null, product_number: int|null, classification: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'product_number' => $this->productNumber,
            'classification' => $this->classification,
        ];
    }
}
