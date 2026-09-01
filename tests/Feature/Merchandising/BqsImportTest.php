<?php

use App\Enums\Merchandising\BqsConflictDecision;
use App\Enums\Merchandising\BqsPackType;
use App\Enums\Merchandising\BqsParseStatus;
use App\Models\Admin\Buyer;
use App\Models\Merchandising\BqsImport;
use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsRowMonth;
use App\Models\Merchandising\BqsRowPackSize;
use App\Models\Merchandising\BqsSheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

/*
|--------------------------------------------------------------------------
| Importing a BQS workbook
|--------------------------------------------------------------------------
|
| The fixture is George's real file: two header rows, 89 columns (A–CK), six
| data rows, eighteen month columns and ten pack-size columns. It is used
| wherever the question is "does this work on what the buyer actually sends".
|
| Everything about the *dynamic* bands is proved against workbooks built here,
| because proving "any month range loads with no migration" needs a second range,
| and a second binary fixture would hide what differs between them.
|
*/

beforeEach(function (): void {
    Storage::fake('local');

    $this->buyer = Buyer::factory()->create(['name' => 'George']);
    $this->date = '2026-09-01';
});

/**
 * Build a BQS workbook with an arbitrary month range and size set.
 *
 * @param  list<string>  $months
 * @param  list<string>  $sizes
 * @param  list<array<string, string|int>>  $rows
 */
function bqsWorkbook(array $months, array $sizes, array $rows, ?callable $tweak = null): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('BQS Report');

    $leaves = [
        'FYE', 'Season', 'Department', 'Buyer', 'Item status', 'Quote ID', 'Category',
        'Sub Category', 'Brand ID', 'Fine Line', 'Vendor Style #', 'Item Description',
        'Pantone Colour', 'Colour Family', 'Color Variant',
    ];
    $bands = array_fill(0, count($leaves), null);

    $bands[] = 'Total BUY Units';
    $leaves[] = 'Store';
    $bands[] = null;
    $leaves[] = 'Ecomm';
    $bands[] = null;
    $leaves[] = 'OMNI';

    foreach ($sizes as $index => $size) {
        $bands[] = $index === 0 ? 'Case Packs' : null;
        $leaves[] = $size;
    }

    foreach ($months as $index => $month) {
        $bands[] = $index === 0 ? 'In DC Units' : null;
        $leaves[] = $month;
    }

    $sheet->fromArray($bands, null, 'A1', true);
    $sheet->fromArray($leaves, null, 'A2', true);

    /*
     * A row-1 band is a merged cell in a real BQS, and the reader treats the merge
     * range as the band's exact extent. Writing the label into one cell and leaving
     * the rest blank would not be the same file George sends.
     */
    $mergeFrom = 16;

    foreach ([3, count($sizes), count($months)] as $width) {
        if ($width > 1) {
            $sheet->mergeCells(sprintf(
                '%s1:%s1',
                Coordinate::stringFromColumnIndex($mergeFrom),
                Coordinate::stringFromColumnIndex($mergeFrom + $width - 1),
            ));
        }

        $mergeFrom += $width;
    }

    foreach ($rows as $offset => $row) {
        $sheet->fromArray(array_values($row), null, 'A'.(3 + $offset), true);
    }

    if ($tweak !== null) {
        $tweak($sheet);
    }

    $path = tempnam(sys_get_temp_dir(), 'bqs').'.xlsx';
    (new XlsxWriter($spreadsheet))->save($path);

    return new UploadedFile($path, 'BQS SYNTHETIC.xlsx', null, null, true);
}

/**
 * One data row for {@see bqsWorkbook()}, in the same column order.
 *
 * @param  list<int>  $sizeQtys
 * @param  list<int>  $monthQtys
 * @return list<string|int>
 */
function bqsRow(string $style, string $colour, string $variant, array $sizeQtys, array $monthQtys): array
{
    return [
        '2028', 'SS', 'GIRLSWEAR', 'JELENA PAPAGEORGE', 'Planning', '', 'D33 GIRLS DRESS',
        'GIRLS DRESSES', 'C-GEORGE PL', '5400', $style, 'GR SS SKATER DRESS',
        $colour, 'GRNLGT', $variant,
        19642, 392, 20034,
        ...$sizeQtys,
        ...$monthQtys,
    ];
}

test('a guest cannot upload', function () {
    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ])->assertRedirect(route('login'));
});

test('the import permission is required, and view alone is not enough', function () {
    $this->actingAs(bqsImporter($this->buyer, BQS_VIEW_PERMISSION));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ])->assertForbidden();
});

test('the buyer must be one the uploader can see', function () {
    $theirs = Buyer::factory()->create(['name' => 'Someone Else']);

    $this->actingAs(bqsImporter($this->buyer));

    // Importing into an invisible buyer would succeed and BuyerScope would then hide
    // the result — a success message over an empty table. See ARCHITECTURE.md §9.2.
    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $theirs->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ])->assertSessionHasErrors('buyer_id');

    expect(BqsImport::withoutBuyerScope()->count())->toBe(0);
});

test('the bqs date is required, because the workbook has none', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'file' => bqsUpload(),
    ])->assertSessionHasErrors('bqs_date');
});

test('the real workbook imports six rows with every band read', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ])->assertRedirect(route('merchandising.bqs.index'));

    $sheet = BqsSheet::withoutBuyerScope()->sole();

    expect($sheet->row_count)->toBe(6)
        ->and($sheet->fye)->toBe('2028')
        ->and($sheet->season)->toBe('SS')
        ->and($sheet->department)->toBe('GIRLSWEAR')
        ->and($sheet->bqs_date->toDateString())->toBe($this->date)
        ->and($sheet->revision_no)->toBe(1)
        ->and($sheet->is_current)->toBeTrue()
        // Revision 1 is its own root — the whole revision chain hangs off this.
        ->and($sheet->root_id)->toBe($sheet->id);

    // 6 rows x 18 months, and 6 x 10 pack sizes (five break, five case).
    expect(BqsRow::count())->toBe(6)
        ->and(BqsRowMonth::count())->toBe(6 * 18)
        ->and(BqsRowPackSize::count())->toBe(6 * 10);
});

test('the static columns are mapped by band and leaf, not by position', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ]);

    $row = BqsRow::where('colour_variant', '503441')->sole();

    expect($row->vendor_style_no)->toBe('GRS74064GX')
        ->and($row->pantone_colour)->toBe('SMOKE GREEN')
        ->and($row->buyer_merchant)->toBe('JELENA PAPAGEORGE')
        // `Store`, `Ecomm` and `OMNI` appear six times each; only the band tells them
        // apart, which is the whole reason the header takes two rows to read.
        ->and($row->initial_set_units_store)->toBe(5502)
        ->and($row->initial_set_units_ecomm)->toBe(196)
        ->and($row->initial_set_units_omni)->toBe(5698)
        ->and($row->total_buy_units_store)->toBe(19642)
        ->and($row->replenishment_units_store)->toBe(14140)
        // The band says "Initial Set Units Per Store", the leaf says "Extra Initial
        // Packs". The leaf wins, because it is what the value is.
        ->and($row->extra_initial_packs)->toBe(0)
        // A label that looks like a ratio and is not.
        ->and($row->pack_ratio)->toBe('FYE28 OPP Dress');
});

test('money keeps its scale instead of becoming a float', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ]);

    // Excel hands this over as 70711.199999999997.
    $row = BqsRow::where('colour_variant', '503441')->sole();

    expect((float) $row->landed_store_cost_store)->toBe(70711.2)
        ->and((float) $row->first_cost)->toBe(2.13);
});

test('the composite week-in-store cell is kept whole and split', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ]);

    $row = BqsRow::where('colour_variant', '503441')->sole();

    expect($row->wm_wk_in_store)->toBe('3 (2027-02-13)')
        ->and($row->wm_wk_in_store_week)->toBe(3)
        ->and($row->wm_wk_in_store_date?->toDateString())->toBe('2027-02-13');
});

test('months and pack sizes become rows in the buyer own order', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ]);

    $row = BqsRow::where('colour_variant', '503441')->sole();

    $months = $row->months()->get();

    // The relation orders by month, so the range reads Nov-2026 → Apr-2028.
    expect($months)->toHaveCount(18)
        ->and($months->first()->month->toDateString())->toBe('2026-11-01')
        ->and($months->first()->month_label)->toBe('November-2026')
        ->and($months->last()->month->toDateString())->toBe('2028-04-01');

    $casePacks = $row->packSizes()->where('pack_type', BqsPackType::Case->value)->get();

    // XS -> S -> M -> L -> XL, the buyer's column order. Sorted as text, XS would
    // land after XL.
    expect($casePacks->pluck('size_label')->all())
        ->toBe(['XS(4/5)', 'S (6)', 'M(7/8)', 'L(10/12)', 'XL(14/16)'])
        ->and($casePacks->pluck('quantity')->all())->toBe([3, 4, 4, 2, 1]);
});

test('a different month range and size set load with no migration', function () {
    $this->actingAs(bqsImporter($this->buyer));

    // Nothing like the fixture: three months two years later, and menswear sizes.
    $file = bqsWorkbook(
        months: ['January-2030', 'February-2030', 'March-2030'],
        sizes: ['S', 'M', 'L', 'XL', 'XXL'],
        rows: [bqsRow('MNS10001A', 'JET BLACK', '900001', [1, 2, 3, 2, 1], [10, 20, 30])],
    );

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => $file,
    ])->assertRedirect(route('merchandising.bqs.index'));

    $row = BqsRow::sole();

    expect($row->months()->pluck('month_label')->all())
        ->toBe(['January-2030', 'February-2030', 'March-2030'])
        ->and($row->packSizes()->pluck('size_label')->all())
        ->toBe(['S', 'M', 'L', 'XL', 'XXL'])
        ->and($row->packSizes()->pluck('quantity')->all())->toBe([1, 2, 3, 2, 1]);
});

test('a missing required column refuses the file and names it', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $file = bqsWorkbook(
        months: ['January-2030'],
        sizes: ['S'],
        rows: [bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10])],
        tweak: fn ($sheet) => $sheet->setCellValue('K2', 'Style Number'),
    );

    $response = $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => $file,
    ]);

    // `error`, not `warning`: no work on other records makes this file readable.
    assertToast($response, 'error');

    expect(BqsSheet::withoutBuyerScope()->count())->toBe(0)
        ->and(BqsImport::withoutBuyerScope()->count())->toBe(0);
});

test('an unrecognised column is a warning, not a refusal', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $file = bqsWorkbook(
        months: ['January-2030'],
        sizes: ['S'],
        rows: [bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10])],
        tweak: function ($sheet): void {
            // George adds a column nobody has mapped. Refusing every import over that
            // would be worse than importing what is understood and saying so.
            $sheet->setCellValue('AZ2', 'Sustainability Index');
            $sheet->setCellValue('AZ3', 'A+');
        },
    );

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => $file,
    ])->assertRedirect(route('merchandising.bqs.index'));

    $sheet = BqsSheet::withoutBuyerScope()->sole();

    expect($sheet->parse_status)->toBe(BqsParseStatus::NeedsReview)
        ->and($sheet->import->payload['unmapped_columns'])->toContain('Sustainability Index');
});

test('the same file twice is silently skipped', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $payload = fn (): array => [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ];

    $this->post(route('merchandising.bqs.import.store'), $payload());
    $this->post(route('merchandising.bqs.import.store'), $payload());

    // Nothing changed, so there is nothing to ask about.
    expect(BqsImport::withoutBuyerScope()->count())->toBe(1)
        ->and(BqsSheet::withoutBuyerScope()->count())->toBe(1);
});

test('a workbook overlapping a held bqs is staged rather than written', function () {
    $this->actingAs($user = bqsImporter($this->buyer));

    $rows = [bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10])];

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], $rows),
    ]);

    // Same identity, different quantity — which is exactly what a reissue is.
    $revised = $rows;
    $revised[0][15] = 25000;

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], $revised),
    ])->assertRedirect(route('merchandising.bqs.index'));

    expect(BqsSheet::withoutBuyerScope()->count())->toBe(1)
        ->and(BqsImport::withoutBuyerScope()->pending()->count())->toBe(1);

    // The dialog reopens on its conflict step, for the uploader and nobody else.
    $this->get(route('merchandising.bqs.index'))
        ->assertInertia(fn ($page) => $page
            ->where('pendingImport.overlapping_rows', 1)
            ->where('pendingImport.collides_with_revision', 1));
});

test('revise stores the next revision and supersedes the previous', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $rows = [bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10])];

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], $rows),
    ]);

    $first = BqsSheet::withoutBuyerScope()->sole();

    $revised = $rows;
    $revised[0][15] = 25000;

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => '2026-10-01',
        'file' => bqsWorkbook(['January-2030'], ['S'], $revised),
    ]);

    $import = BqsImport::withoutBuyerScope()->pending()->sole();

    $this->post(route('merchandising.bqs.import.resolve', $import), [
        'decision' => BqsConflictDecision::Revise->value,
    ])->assertRedirect(route('merchandising.bqs.index'));

    $sheets = BqsSheet::withoutBuyerScope()->orderBy('revision_no')->get();

    expect($sheets)->toHaveCount(2)
        ->and($sheets[0]->is_current)->toBeFalse()
        ->and($sheets[1]->revision_no)->toBe(2)
        ->and($sheets[1]->is_current)->toBeTrue()
        // Both revisions hang off the same root, which is revision 1.
        ->and($sheets[1]->root_id)->toBe($first->id)
        // Each revision keeps the date entered with its own upload.
        ->and($sheets[1]->bqs_date->toDateString())->toBe('2026-10-01')
        ->and($sheets[1]->rows()->sole()->total_buy_units_store)->toBe(25000);

    expect(BqsImport::withoutBuyerScope()->pending()->count())->toBe(0);
});

test('skip leaves the held bqs alone but keeps the file on record', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $rows = [bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10])];

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], $rows),
    ]);

    $revised = $rows;
    $revised[0][15] = 25000;

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], $revised),
    ]);

    $import = BqsImport::withoutBuyerScope()->pending()->sole();

    $this->post(route('merchandising.bqs.import.resolve', $import), [
        'decision' => BqsConflictDecision::Skip->value,
    ]);

    // The workbook was received; that is a fact worth holding even though it
    // produced no revision. This is why imports and sheets are different tables.
    expect(BqsSheet::withoutBuyerScope()->count())->toBe(1)
        ->and(BqsSheet::withoutBuyerScope()->sole()->rows()->sole()->total_buy_units_store)->toBe(19642)
        ->and(BqsImport::withoutBuyerScope()->count())->toBe(2)
        ->and(BqsImport::withoutBuyerScope()->pending()->count())->toBe(0);
});

test('overwrite replaces the revision in place and needs the delete permission', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $rows = [bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10])];

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], $rows),
    ]);

    $revised = $rows;
    $revised[0][15] = 25000;

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], $revised),
    ]);

    $import = BqsImport::withoutBuyerScope()->pending()->sole();

    // `import` is not enough: overwriting destroys a stored revision and its rows.
    $this->post(route('merchandising.bqs.import.resolve', $import), [
        'decision' => BqsConflictDecision::Overwrite->value,
    ])->assertForbidden();

    $this->actingAs(bqsImporter(
        $this->buyer,
        BQS_IMPORT_PERMISSION,
        BQS_VIEW_PERMISSION,
        BQS_DELETE_PERMISSION,
    ));

    // A different user may not answer someone else's staged import.
    $this->post(route('merchandising.bqs.import.resolve', $import), [
        'decision' => BqsConflictDecision::Overwrite->value,
    ])->assertNotFound();
});

test('a workbook straddling two held bqs records is refused', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $first = [bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10])];
    $second = [bqsRow('MNS20002B', 'PURE WHITE', '900002', [1], [10])];

    foreach ([$first, $second] as $rows) {
        $this->post(route('merchandising.bqs.import.store'), [
            'buyer_id' => $this->buyer->id,
            'bqs_date' => $this->date,
            'file' => bqsWorkbook(['January-2030'], ['S'], $rows),
        ]);
    }

    expect(BqsSheet::withoutBuyerScope()->count())->toBe(2);

    // Rows from both. It is a revision of neither, and picking one would silently
    // orphan the other.
    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], [...$first, ...$second]),
    ]);

    expect(BqsSheet::withoutBuyerScope()->count())->toBe(2)
        ->and(BqsImport::withoutBuyerScope()->pending()->count())->toBe(0);
});

test('two rows for the same style and colour refuse the file', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $row = bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10]);

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], [$row, $row]),
    ]);

    // One row per style and colour is the rule; a duplicate means two rows disagree
    // about a quantity with no way to tell which is meant.
    expect(BqsSheet::withoutBuyerScope()->count())->toBe(0);
});

test('a row whose own totals disagree is stored unchanged and flagged', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $row = bqsRow('MNS10001A', 'JET BLACK', '900001', [1], [10]);
    $row[17] = 99999; // OMNI, which should be Store + Ecomm.

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsWorkbook(['January-2030'], ['S'], [$row]),
    ]);

    $sheet = BqsSheet::withoutBuyerScope()->sole();

    // The workbook is the source of truth. Recomputing it silently would make the
    // application disagree with the document it claims to hold.
    expect($sheet->rows()->sole()->total_buy_units_omni)->toBe(99999)
        ->and($sheet->parse_status)->toBe(BqsParseStatus::NeedsReview)
        ->and($sheet->payload['warnings'])->not->toBeEmpty();
});

test('a non-workbook upload is refused', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
    ])->assertSessionHasErrors('file');
});

test('the detail page pivots months and sizes back into columns', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ]);

    $sheet = BqsSheet::withoutBuyerScope()->sole();

    $this->get(route('merchandising.bqs.show', $sheet))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('merchandising/bqs/show')
            ->has('monthColumns', 18)
            ->has('packColumns', 10)
            ->has('rows', 6)
            ->where('monthColumns.0.label', 'November-2026'));
});

test('all four of the workbook colour fields reach the detail page', function () {
    $this->actingAs(bqsImporter($this->buyer));

    $this->post(route('merchandising.bqs.import.store'), [
        'buyer_id' => $this->buyer->id,
        'bqs_date' => $this->date,
        'file' => bqsUpload(),
    ]);

    $sheet = BqsSheet::withoutBuyerScope()->sole();

    /*
     * The source has four separate colour columns and the detail table renders all
     * of them. They arrive through `BqsController::rowFields()`, which is a hand-kept
     * list — trimming an entry there empties a column on the page with nothing else
     * failing, which is exactly what this pins.
     *
     * `other_colour` is null in every row of this file and is asserted as present
     * rather than as a value: the column exists in the workbook, so it is rendered,
     * and a silently dropped field would be worse than a column of dashes.
     */
    $this->get(route('merchandising.bqs.show', $sheet))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Row 3 of the workbook, which is also row 0 here — the rows relation
            // is ordered by `line_no`, so the page reads in the buyer's own order.
            ->where('rows.0.line_no', 3)
            ->where('rows.0.pantone_colour', 'SMOKE GREEN')
            ->where('rows.0.colour_family', 'GRNLGT')
            ->where('rows.0.colour_variant', '503441')
            ->has('rows.0.other_colour'));
});
