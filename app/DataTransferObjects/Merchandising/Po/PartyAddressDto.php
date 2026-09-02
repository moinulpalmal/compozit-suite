<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * One party's name and address, read out of a column in the document's address block.
 *
 * The block prints five parties side by side in fixed-width columns, so a party is
 * a *column* of text rather than a record — which is why the lines are numbered
 * rather than named, and why `$raw` keeps the original cells joined. When a column
 * is laid out unusually, `$raw` is the only field guaranteed to be complete.
 */
final readonly class PartyAddressDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $line1 = null,
        public ?string $line2 = null,
        public ?string $line3 = null,
        public ?string $line4 = null,
        public ?string $country = null,
        public ?string $raw = null,
    ) {}

    /**
     * @return array{name: string|null, line1: string|null, line2: string|null, line3: string|null, line4: string|null, country: string|null, raw: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'line3' => $this->line3,
            'line4' => $this->line4,
            'country' => $this->country,
            'raw' => $this->raw,
        ];
    }
}
