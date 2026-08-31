<?php

namespace App\Services\Merchandising\PoParser\FieldExtractors;

use App\DataTransferObjects\Merchandising\Po\PoHeaderDto;
use App\Services\Merchandising\PoParser\Support\Capture;

/**
 * Reads the banner at the top of a purchase order's pages.
 *
 * `Revised Date` and its `By:` are the two fields the import path depends on beyond
 * display — they are Walmart's own statement of which revision this document is.
 *
 * The `.*?By:` patterns are lazy on purpose: several labels on the same line end in
 * `By:`, and a greedy match would walk past the intended one to the last on the line.
 */
final class PageHeaderExtractor
{
    /**
     * @param  list<string>  $headerLines
     */
    public function build(array $headerLines, int $pageCount = 0): PoHeaderDto
    {
        $text = implode("\n", $headerLines);

        return new PoHeaderDto(
            poNumber: Capture::text('/Purchase Order:\s*(\d{10})/', $text),
            status: Capture::text('/Status:\s*(\w+)/', $text),
            quoteId: Capture::text('/Quote Id:\s*(\d+)/', $text),
            documentType: Capture::flag('/IMPORT\s+PURCHASE\s+ORDER/', $text) ? 'IMPORT_PURCHASE_ORDER' : null,
            createDate: Capture::date('/Create Date:\s*(\d{2}\/\d{2}\/\d{4})/', $text),
            negotiationDate: Capture::date('/Negotiation Date:\s*(\d{2}\/\d{2}\/\d{4})/', $text),
            exchangeRate: Capture::float('/Exchange Rate:\s*([\d.]+)/', $text),
            bidCurrency: Capture::text('/Bid Currency:\s*([A-Z]{3})/', $text),
            revisedDate: Capture::dateTime('/Revised Date:\s*(\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2})/', $text),
            revisedBy: Capture::text('/Revised Date:.*?By:\s*(\w+)/', $text),
            preclassStatus: Capture::text('/Preclass Status:\s*(\w+)/', $text),
            preclassApprovalDate: Capture::date('/Preclass Approval Date:\s*(\d{2}\/\d{2}\/\d{4})/', $text),
            preclassApprovalBy: Capture::text('/Preclass Approval Date:.*?By:\s*(\w+)/', $text),
            printDate: Capture::dateTime('/Print Date:\s*(\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2})/', $text),
            printedBy: Capture::text('/Print Date:.*?By:\s*(\w+)/', $text),
            pageCount: $pageCount,
        );
    }
}
