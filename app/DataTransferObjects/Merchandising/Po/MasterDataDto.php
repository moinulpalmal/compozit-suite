<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * The organisational block: who ordered, who supplies, and which offices are involved.
 *
 * Everything here is Walmart's own reference data — division and department numbers,
 * their buyers' names, the vendor and bank identifiers they issued. None of it is
 * resolved against this application's own master data ([`ARCHITECTURE.md §9.4`](../../../../ARCHITECTURE.md#94-master-data));
 * it is recorded as printed.
 */
final readonly class MasterDataDto
{
    public function __construct(
        public ?int $division = null,
        public ?int $department = null,
        public ?string $destination = null,
        public ?string $orderingBuyer = null,
        public ?string $managingBuyer = null,
        public ?string $creditOffice = null,
        public ?string $logisticsOffice = null,
        public ?string $vendorName = null,
        public ?string $creationOffice = null,
        public ?string $hostVendor = null,
        public ?string $classType = null,
        public ?string $vendorNumber = null,
        public ?string $bankNumber = null,
        public ?string $primaryBeneficiary = null,
        public ?string $secondaryBeneficiary = null,
        public ?string $buyingAgent1 = null,
        public ?string $buyingAgent2 = null,
    ) {}

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'division' => $this->division,
            'department' => $this->department,
            'destination' => $this->destination,
            'ordering_buyer' => $this->orderingBuyer,
            'managing_buyer' => $this->managingBuyer,
            'credit_office' => $this->creditOffice,
            'logistics_office' => $this->logisticsOffice,
            'vendor_name' => $this->vendorName,
            'creation_office' => $this->creationOffice,
            'host_vendor' => $this->hostVendor,
            'class_type' => $this->classType,
            'vendor_number' => $this->vendorNumber,
            'bank_number' => $this->bankNumber,
            'primary_beneficiary' => $this->primaryBeneficiary,
            'secondary_beneficiary' => $this->secondaryBeneficiary,
            'buying_agent1' => $this->buyingAgent1,
            'buying_agent2' => $this->buyingAgent2,
        ];
    }
}
