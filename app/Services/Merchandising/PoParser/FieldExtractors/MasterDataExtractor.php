<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\MasterDataDto;
use App\Services\Merchandising\PoParser\Support\Capture;

/**
 * Reads the organisational block — divisions, buyers, offices, vendor and bank ids.
 *
 * Two pattern shapes appear here and the difference is deliberate. `\s{2,}` ends a
 * value that has another labelled column to its right, because two or more spaces is
 * what separates columns in a fixed-width row. `(.+)$/m` is for the value that runs
 * to the end of its line, where there is no following column to stop at.
 */
final class MasterDataExtractor
{
    public function build(string $text): MasterDataDto
    {
        return new MasterDataDto(
            division: Capture::int('/Division:\s*(\d+)/', $text),
            department: Capture::int('/Department:\s*(\d+)/', $text),
            destination: Capture::text('/Destination:\s*(.+?)\s{2,}/', $text),
            orderingBuyer: Capture::text('/Ordering Buyer:\s*(.+?)\s{2,}/', $text),
            managingBuyer: Capture::text('/Managing Buyer:\s*(.+?)\s{2,}/', $text),
            creditOffice: Capture::text('/Credit Office:\s*(.+)$/m', $text),
            logisticsOffice: Capture::text('/Logistics Office:\s*(.+)$/m', $text),
            vendorName: Capture::text('/Vendor Name:\s*(.+?)\s{2,}/', $text),
            creationOffice: Capture::text('/Creation Office:\s*(.+)$/m', $text),
            hostVendor: Capture::text('/Host Vendor:\s*(\S+)/', $text),
            classType: Capture::text('/Class Type:\s*(.+)$/m', $text),
            vendorNumber: Capture::text('/VENDOR#:\s*(\d+)/', $text),
            bankNumber: Capture::text('/BANK:\s*(\d+)/', $text),
            primaryBeneficiary: Capture::text('/PRIMARY BENEFICIARY:\s*(\d+)/', $text),
            secondaryBeneficiary: Capture::text('/SECONDARY BENEFICIARY:\s*(\S*)/', $text),
            buyingAgent1: Capture::text('/BUYING AGENT1:\s*(\d*)/', $text),
            buyingAgent2: Capture::text('/BUYING AGENT2:\s*(\S*)/', $text),
        );
    }
}
