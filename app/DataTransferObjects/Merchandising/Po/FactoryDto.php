<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The manufacturing factory named on the order.
 *
 * The document prints an id followed by an unlabelled address of variable length,
 * so the lines are kept as a list and only the two things that can be inferred
 * reliably — the name from the first line, the country from the last token of the
 * last — are lifted out.
 */
final readonly class FactoryDto
{
    /**
     * @param  list<string>  $addressLines
     */
    public function __construct(
        public ?string $factoryId = null,
        public ?string $name = null,
        public array $addressLines = [],
        public ?string $countryCode = null,
    ) {}

    /**
     * @return array{factory_id: string|null, name: string|null, address_lines: list<string>, country_code: string|null}
     */
    public function toArray(): array
    {
        return [
            'factory_id' => $this->factoryId,
            'name' => $this->name,
            'address_lines' => $this->addressLines,
            'country_code' => $this->countryCode,
        ];
    }
}
