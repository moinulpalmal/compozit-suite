<?php

use App\Support\SizeLabel;

/*
|--------------------------------------------------------------------------
| Comparing size labels across two documents
|--------------------------------------------------------------------------
|
| A BQS workbook and a Walmart purchase order are typed from different
| templates and spell the same size differently — `XS(4/5)` against `XS-4-5`.
| Neither spelling is wrong and neither is rewritten on the way in, so the
| comparison is what has to absorb the difference.
|
| The pairs below are the ones actually observed: the BQS spellings come from
| the `Break Packs` band of the reference workbook, the PO spellings from
| `po-parser.parsing.size_vocab`.
|
*/

test('separator style does not change a size', function (string $bqs, string $po) {
    expect(SizeLabel::normalise($bqs))->toBe(SizeLabel::normalise($po))
        ->and(SizeLabel::matches($bqs, $po))->toBeTrue();
})->with([
    'parentheses against hyphens' => ['XS(4/5)', 'XS-4-5'],
    'inner slash against inner hyphen' => ['L(10/12)', 'L(10-12)'],
    'a stray space in the workbook' => ['M (7/8)', 'M(7/8)'],
    'kids sizes agree once normalised' => ['XL(14/16)', 'XL(14-16)'],
]);

test('infant sizes are already identical on both sides and survive intact', function (string $label) {
    expect(SizeLabel::normalise($label))->toBe($label);
})->with(['0-3M', '3-6M', '6-12M', '12-18M', '18-24M']);

test('different sizes stay different', function () {
    expect(SizeLabel::matches('3-6M', '6-12M'))->toBeFalse()
        ->and(SizeLabel::matches('S', 'XS'))->toBeFalse()
        ->and(SizeLabel::matches('M(7/8)', 'L(10/12)'))->toBeFalse();
});

/*
 * An empty label matching everything would silently link a sizeless line to the
 * first row it met, so it matches nothing at all — including another empty label.
 */
test('an empty or unreadable label matches nothing', function (?string $label) {
    expect(SizeLabel::normalise($label))->toBe('')
        ->and(SizeLabel::matches($label, $label))->toBeFalse()
        ->and(SizeLabel::matches($label, '3-6M'))->toBeFalse();
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
    'separators only' => ['(/-)'],
]);

test('case and surrounding whitespace are ignored', function () {
    expect(SizeLabel::matches('  3-6m  ', '3-6M'))->toBeTrue()
        ->and(SizeLabel::matches('xs(4/5)', 'XS-4-5'))->toBeTrue();
});
