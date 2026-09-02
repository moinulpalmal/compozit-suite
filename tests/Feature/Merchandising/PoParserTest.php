<?php

use App\Enums\Merchandising\PoParseStatus;
use App\Exceptions\Merchandising\PoParser\PoValidationException;
use App\Exceptions\Merchandising\PoParser\UnsupportedFileTypeException;
use App\Services\Merchandising\PoParser\ParserService;

/*
|--------------------------------------------------------------------------
| The purchase-order document parser
|--------------------------------------------------------------------------
|
| Three fixtures of the same purchase order, one per supported format, in
| `tests/Fixtures/Merchandising/`. They are **redacted copies** of a real
| Walmart document: every company name, personal name, address, identifier and
| cost was replaced with a synthetic value of exactly the same length, because
| the parser reads fixed-width column positions and a shorter replacement would
| shift every cell to its right — the fixture would then stop exercising the
| layout it exists to prove.
|
| The three cases below assert the *same* numbers, which is the point: `.docx`
| is read in-process, `.doc` goes through LibreOffice, and `.pdf` through Xpdf's
| pdftotext. Three unrelated toolchains agreeing on 3 orders × 4 packs × 5 line
| items is what says the extraction is right rather than merely self-consistent.
|
| **Nothing here skips when a binary is missing.** The demo this was ported from
| did exactly that, and the consequence was a repository whose only test silently
| passed without running. LibreOffice and pdftotext are documented prerequisites
| (documentation/deployment.md); if one is absent this fails loudly, which is the
| correct outcome.
|
*/

/** The redacted fixtures, one per supported format. */
function poFixture(string $extension): string
{
    return __DIR__.'/../../Fixtures/Merchandising/PO-SAMPLE-WALMART.'.$extension;
}

dataset('po fixtures', [
    'docx (in-process, ext-zip)' => ['docx'],
    'doc (via LibreOffice)' => ['doc'],
    'pdf (via Xpdf pdftotext)' => ['pdf'],
]);

test('every supported format parses to the same purchase orders', function (string $extension) {
    $result = app(ParserService::class)->parse(poFixture($extension), 'PO-SAMPLE-WALMART.'.$extension);

    expect($result->detectedFileType)->toBe($extension)
        ->and($result->pageCount)->toBe(27)
        ->and($result->poCount)->toBe(3)
        ->and($result->status)->toBe(PoParseStatus::Success)
        ->and($result->overallConfidence)->toBe(1.0)
        ->and($result->globalWarnings)->toBe([]);

    // The three PO numbers the document holds, in order.
    expect(array_map(fn ($po) => $po->header?->poNumber, $result->purchaseOrders))
        ->toBe(['1000000001', '1000000002', '1000000003']);
})->with('po fixtures');

test('every format yields the same packs and line items, fully populated', function (string $extension) {
    $result = app(ParserService::class)->parse(poFixture($extension), 'PO-SAMPLE-WALMART.'.$extension);

    foreach ($result->purchaseOrders as $po) {
        expect($po->packs)->toHaveCount(4);
        expect($po->tariffs)->toHaveCount(2);

        foreach ($po->packs as $pack) {
            expect($pack->lineItems)->toHaveCount(5);

            foreach ($pack->lineItems as $item) {
                // Every one of these comes from a different part of the three
                // printed lines a line item spans, so a single misaligned column
                // shows up as one of them being empty.
                expect($item->itemNumber)->not->toBeEmpty()
                    ->and($item->upcNumber)->not->toBeEmpty()
                    ->and($item->productNumber)->not->toBeEmpty()
                    ->and($item->vendorStockNumber)->not->toBeEmpty()
                    ->and($item->itemDescription1)->not->toBeEmpty()
                    ->and($item->uomCode)->not->toBeEmpty()
                    ->and($item->color)->not->toBeEmpty()
                    ->and($item->size)->not->toBeEmpty()
                    ->and($item->quantity)->toBeGreaterThan(0);

                /*
                 * `mfgStockNumber` is deliberately absent from that list. Walmart
                 * leaves the MFG STOCK NBR column blank on this order — the row runs
                 * "…3  051156416<gap>0000024640803  0821729901696" — so null is the
                 * correct reading, not a missed capture. Asserting it populated
                 * would be asserting a fact about the document that is not true.
                 */
            }
        }
    }
})->with('po fixtures');

test('all three formats produce an identical template fingerprint', function () {
    $parser = app(ParserService::class);

    $fingerprints = array_map(
        fn (string $ext): string => $parser->parse(poFixture($ext), 'x.'.$ext)->templateFingerprint,
        ['docx', 'doc', 'pdf'],
    );

    // The fingerprint is the ordered set of sections the state machine found. If
    // one toolchain lost a section the others kept, these diverge — which is the
    // cheapest possible detector for a converter regression.
    expect(array_unique($fingerprints))->toHaveCount(1);
});

test('the header carries the revision markers the import path keys on', function () {
    $result = app(ParserService::class)->parse(poFixture('docx'), 'PO-SAMPLE-WALMART.docx');

    $header = $result->purchaseOrders[0]->header;

    // Walmart states the revision itself; the import does not invent a counter.
    expect($header->revisedDate)->toBe('2026-07-06T20:35:01')
        ->and($header->revisedBy)->not->toBeEmpty()
        ->and($header->quoteId)->toBe('90000001')
        ->and($header->documentType)->toBe('IMPORT_PURCHASE_ORDER');
});

test('dates are read month-first, as the document prints them', function () {
    $result = app(ParserService::class)->parse(poFixture('docx'), 'PO-SAMPLE-WALMART.docx');

    // 06/26/2026 cannot be read day-first, which is what pins the order. For the
    // first twelve days of a month both readings parse, so a day-first bug would
    // corrupt a minority of dates and pass any test that avoided this one.
    expect($result->purchaseOrders[0]->header->preclassApprovalDate)->toBe('2026-06-26');
});

test('a file that is not a supported document is refused by its signature', function () {
    $path = tempnam(sys_get_temp_dir(), 'po').'.docx';
    file_put_contents($path, 'this is plain text wearing a .docx extension');

    expect(fn () => app(ParserService::class)->parse($path, 'fake.docx'))
        ->toThrow(UnsupportedFileTypeException::class);

    @unlink($path);
});

test('a readable document with no Walmart pages is refused with an explanation', function () {
    // A valid, parseable DOCX — the fixture's own container — with its text
    // replaced, so this exercises "read it, found no purchase orders" rather
    // than "could not read it".
    $path = tempnam(sys_get_temp_dir(), 'po').'.docx';
    copy(poFixture('docx'), $path);

    $zip = new ZipArchive;
    $zip->open($path);
    $zip->addFromString('word/document.xml',
        '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        .'<w:body><w:p><w:r><w:t>Nothing to see here.</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    expect(fn () => app(ParserService::class)->parse($path, 'empty.docx'))
        ->toThrow(PoValidationException::class);

    @unlink($path);
});
