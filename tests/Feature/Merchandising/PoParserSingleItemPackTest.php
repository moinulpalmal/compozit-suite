<?php

use App\Enums\Merchandising\ParserState;
use App\Services\Merchandising\PoParser\FieldExtractors\LineItemRowExtractor;
use App\Services\Merchandising\PoParser\PurchaseOrderBuilder;
use App\Services\Merchandising\PoParser\StateMachine\SectionStateMachine;

/*
|--------------------------------------------------------------------------
| Single-item packs, and the infant size run
|--------------------------------------------------------------------------
|
| `PoParserTest` proves the girls' reference document: four assortment packs of
| five sizes each, every row carrying a size. A second real shape exists and
| broke four different ways at once — an infant purchase order of **five packs
| holding one line apiece**, each marked `Assortment Ind: Single Item Pack`, the
| first of which prints no `SIZE` column at all.
|
| The lines below are built at the byte offsets measured on those documents:
|
| ```text
| COLOR           SIZE             ITEM SALES CHAN      QUANTITY     ITEM NBR
| 0               16               33                   51           64
| ```
|
| That third column matters. `ITEM SALES CHAN` sits between the size and the
| quantity, so a size read as "everything up to QUANTITY" swallows
| `OMNI CHANNEL IT` along with it.
|
| These are text-level rather than fixture-level tests on purpose: the four
| defects are all in line composition, and expressing the geometry in the test
| is what makes an offset regression readable. The binary fixtures in
| `tests/Fixtures/Merchandising/` still cover the toolchains.
|
*/

/** Place each cell at the byte offset its column heading occupies. */
function poFixedWidth(array $cells): string
{
    $line = '';

    foreach ($cells as $offset => $text) {
        $line = str_pad($line, $offset).$text;
    }

    return $line;
}

/** The column heading a pack prints above its rows. */
function poColumnHeading(bool $withSize): string
{
    return poFixedWidth(array_filter([
        0 => 'COLOR',
        16 => $withSize ? 'SIZE' : '',
        33 => 'ITEM SALES CHAN',
        51 => 'QUANTITY',
        64 => 'ITEM NBR',
        119 => 'MFG STOCK NBR',
    ]));
}

/** One colour row, laid out under those headings. */
function poColourRow(string $color, ?string $size, string $itemNumber, string $product, string $upc): string
{
    return poFixedWidth(array_filter([
        0 => $color,
        16 => $size ?? '',
        33 => 'OMNI CHANNEL IT',
        58 => '2',
        64 => $itemNumber,
        139 => $product,
        157 => $upc,
    ]));
}

/**
 * One pack's worth of document lines, in the order the real file prints them.
 *
 * The sequence is the one a real order produces — page header, product, tariff,
 * pack cost, footer, line-item header, rows, comments — because the state
 * machine only reaches `LineItemRows` along that path.
 *
 * @return list<string>
 */
function poPackLines(int $packNumber, ?string $size, string $itemNumber, string $product, string $upc): array
{
    return [
        'Purchase Order: 2850907133                     WAL-MART CANADA CORP.        Page: '.$packNumber,
        'PRODUCT: CD GENDER INCLUSIVE JOGGER',
        'TARIFF# CUSTOMS DESCRIPTION',
        'Pack Description:  8PC CD INFANT UNISEX JOGGER    SUBCLASS/FINELINE: 00/0238  Pack #: '.$packNumber,
        '       Total Cartons per Line: 40',
        'Wal-Mart Confidential',
        'Item (L x W x H): 2.743 2.743 2.743',
        '  Vendor Stock: CDY33205IU',
        'Assortment Ind: Single Item Pack',
        poColumnHeading($size !== null),
        poColourRow('RED-JESTER RED', $size, $itemNumber, $product, $upc),
        '                                      CDY33205IU           CD INFNT UNISEX JOGG',
        '                                           JOGGER                    CA CD INFANT UNISEX JOGGER   1.0000       EA',
        ' PACK COMMENTS:              GENERAL COMMENTS:',
        'Wal-Mart Confidential',
    ];
}

/** The five packs of the reference infant order — the first with no size column. */
function poInfantOrderLines(): array
{
    $packs = [
        [8, null, '050087643', '0000021683033', '0821729682298'],
        [9, '3-6M', '050087646', '0000021683034', '0821729682304'],
        [10, '6-12M', '050087647', '0000021683035', '0821729682311'],
        [11, '12-18M', '050087644', '0000021683036', '0821729682328'],
        [12, '18-24M', '050087645', '0000021683037', '0821729682335'],
    ];

    $lines = [];
    $index = 0;

    foreach ($packs as [$number, $size, $item, $product, $upc]) {
        foreach (poPackLines($number, $size, $item, $product, $upc) as $text) {
            $lines[] = ['index' => $index++, 'text' => $text];
        }
    }

    return $lines;
}

/*
|--------------------------------------------------------------------------
| The defect that misattributed line items across packs
|--------------------------------------------------------------------------
*/

test('a pack with no SIZE column still opens a line-item rows section', function () {
    $segments = app(SectionStateMachine::class)->run(poInfantOrderLines());

    $rowSegments = array_values(array_filter(
        $segments,
        fn (array $segment): bool => $segment['state'] === ParserState::LineItemRows,
    ));

    // One per pack. The transition once demanded a SIZE heading, so the first
    // pack produced none and every later pack inherited the next pack's rows.
    expect($rowSegments)->toHaveCount(5);
});

test('every pack keeps its own line item rather than the next pack s', function () {
    $segments = app(SectionStateMachine::class)->run(poInfantOrderLines());
    $order = app(PurchaseOrderBuilder::class)->build($segments, 5);

    expect($order->packs)->toHaveCount(5);

    $pairs = [];

    foreach ($order->packs as $pack) {
        expect($pack->lineItems)->toHaveCount(1);

        $pairs[$pack->packNumber] = $pack->lineItems[0]->itemNumber;
    }

    // Pack 8's row was lost entirely and pack 12 was left empty.
    expect($pairs)->toBe([
        8 => '050087643',
        9 => '050087646',
        10 => '050087647',
        11 => '050087644',
        12 => '050087645',
    ]);
});

/*
|--------------------------------------------------------------------------
| Colour and size are read independently
|--------------------------------------------------------------------------
*/

test('a sizeless pack keeps its colour', function () {
    $segments = app(SectionStateMachine::class)->run(poInfantOrderLines());
    $order = app(PurchaseOrderBuilder::class)->build($segments, 5);

    $first = $order->packs[0]->lineItems[0];

    expect($first->color)->toBe('RED-JESTER RED')
        ->and($first->size)->toBeNull();
});

test('the ITEM SALES CHAN column is never mistaken for the size', function () {
    $segments = app(SectionStateMachine::class)->run(poInfantOrderLines());
    $order = app(PurchaseOrderBuilder::class)->build($segments, 5);

    $sizes = [];

    foreach ($order->packs as $pack) {
        $sizes[] = $pack->lineItems[0]->size;
        expect($pack->lineItems[0]->color)->toBe('RED-JESTER RED');
    }

    expect($sizes)->toBe([null, '3-6M', '6-12M', '12-18M', '18-24M']);
});

/*
|--------------------------------------------------------------------------
| The vocabulary fallback, and the trap inside it
|--------------------------------------------------------------------------
*/

test('a bare S in the vocabulary does not match inside a colour name', function () {
    // Sizes come from the buyer's BQS, whose band carries bare S, M and L
    // alongside the infant run. An unanchored alternation finds the S in
    // JESTER and reports the colour as "RED-JE".
    $vocabulary = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '0-3M', '3-6M', '6-12M', '12-18M', '18-24M'];

    // No column heading in this segment, so the vocabulary is what answers.
    $lines = [
        ['index' => 0, 'text' => poColourRow('RED-JESTER RED', '3-6M', '050087646', '0000021683034', '0821729682304')],
        ['index' => 1, 'text' => '                                      CDY33205IU           CD INFNT UNISEX JOGG'],
        ['index' => 2, 'text' => '                                           JOGGER      CA CD INFANT UNISEX JOGGER   1.0000       EA'],
    ];

    $items = app(LineItemRowExtractor::class)->build($lines, $vocabulary);

    expect($items)->toHaveCount(1)
        ->and($items[0]->color)->toBe('RED-JESTER RED')
        ->and($items[0]->size)->toBe('3-6M');
});

test('the colour survives a size the vocabulary has never heard of', function () {
    $lines = [
        ['index' => 0, 'text' => poColourRow('RED-JESTER RED', '9-11Y', '050087646', '0000021683034', '0821729682304')],
        ['index' => 1, 'text' => '                                      CDY33205IU           CD INFNT UNISEX JOGG'],
        ['index' => 2, 'text' => '                                           JOGGER      CA CD INFANT UNISEX JOGGER   1.0000       EA'],
    ];

    $items = app(LineItemRowExtractor::class)->build($lines, ['3-6M', '6-12M']);

    // An unknown size costs the size and nothing else. It used to cost the
    // colour too, which is what left every line unlinkable to its BQS row.
    expect($items[0]->color)->toBe('RED-JESTER RED')
        ->and($items[0]->size)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| What the validator says about it
|--------------------------------------------------------------------------
*/

test('a single-item pack is not reported as missing four line items', function () {
    $segments = app(SectionStateMachine::class)->run(poInfantOrderLines());
    $order = app(PurchaseOrderBuilder::class)->build($segments, 5);

    $codes = array_map(fn ($warning): string => $warning->code, $order->warnings);

    // V5 expected five lines of every pack, so a five-pack single-item order
    // raised five warnings and graded needs_review for nothing.
    expect($codes)->not->toContain('V5');
});

test('the pack that printed no size is reported once', function () {
    $segments = app(SectionStateMachine::class)->run(poInfantOrderLines());
    $order = app(PurchaseOrderBuilder::class)->build($segments, 5);

    $sizeWarnings = array_values(array_filter(
        $order->warnings,
        fn ($warning): bool => $warning->code === 'V14',
    ));

    expect($sizeWarnings)->toHaveCount(1)
        ->and($sizeWarnings[0]->message)->toContain('Pack 8');
});

test('a colour that cannot be read is reported', function () {
    $lines = [
        ['index' => 0, 'text' => poFixedWidth([58 => '2', 64 => '050087646', 139 => '0000021683034', 157 => '0821729682304'])],
        ['index' => 1, 'text' => '                                      CDY33205IU           CD INFNT UNISEX JOGG'],
        ['index' => 2, 'text' => '                                           JOGGER      CA CD INFANT UNISEX JOGGER   1.0000       EA'],
    ];

    $items = app(LineItemRowExtractor::class)->build($lines, ['3-6M']);

    // Nothing said so before: a null colour graded a clean success, and the
    // line simply reported as having no BQS row.
    expect($items[0]->color)->toBeNull();
});
