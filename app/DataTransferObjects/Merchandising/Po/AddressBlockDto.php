<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The five parties a Walmart purchase order names, in the order the document prints them.
 *
 * Every one is nullable: a purchase order routinely leaves the secondary
 * beneficiary or the buying agent blank, and an empty column is normal data rather
 * than a parse failure.
 */
final readonly class AddressBlockDto
{
    public function __construct(
        public ?PartyAddressDto $vendor = null,
        public ?PartyAddressDto $bank = null,
        public ?PartyAddressDto $primaryBeneficiary = null,
        public ?PartyAddressDto $secondaryBeneficiary = null,
        public ?PartyAddressDto $buyingAgent = null,
    ) {}

    /**
     * @return array<string, array<string, string|null>|null>
     */
    public function toArray(): array
    {
        return [
            'vendor' => $this->vendor?->toArray(),
            'bank' => $this->bank?->toArray(),
            'primary_beneficiary' => $this->primaryBeneficiary?->toArray(),
            'secondary_beneficiary' => $this->secondaryBeneficiary?->toArray(),
            'buying_agent' => $this->buyingAgent?->toArray(),
        ];
    }
}
