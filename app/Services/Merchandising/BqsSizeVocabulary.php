<?php

namespace App\Services\Merchandising;

use App\Models\Merchandising\BqsRow;
use App\Models\Merchandising\BqsRowPackSize;
use App\Models\Merchandising\BqsSheet;
use App\Services\Merchandising\PoParser\FieldExtractors\LineItemRowExtractor;

/**
 * The size labels a buyer's current BQS knows about.
 *
 * ## Why the BQS and not a config list
 *
 * `po-parser.parsing.size_vocab` was a fixed five-entry list written for one girls'
 * programme (`XS-4-5 … XL(14-16)`). An infant purchase order prints `0-3M … 18-24M`,
 * which overlaps it **not at all** — so every line parsed with no size, and because
 * {@see LineItemRowExtractor} once derived the colour from the size's position, with no
 * colour either. Nothing then linked to the BQS, and nothing said so.
 *
 * The BQS is the better source because it already carries the answer: the
 * `Break Packs` / `Case Packs` bands are headed with the size run, stored as
 * {@see BqsRowPackSize} rows precisely because those headers are *data*
 * (ARCHITECTURE.md §5, Module 3). The reference workbook's band carries 22 labels
 * spanning every programme at once — `XS…XXL`, `XS(4/5)…XL(14/16)`, `2T…5T`,
 * `0-3M…18-24M` — so it is a genuine superset rather than this season's slice, and a
 * new size range arrives with the buyer's own workbook instead of a deploy.
 *
 * ## This is a fallback, not the mechanism
 *
 * {@see LineItemRowExtractor} reads the size from the **column position** the pack's
 * own header line gives it, and needs no vocabulary to do so. The list returned here is
 * only for a row whose header could not be read. That ordering matters: the vocabulary
 * contains bare `S`, `M` and `L`, and a vocabulary-first matcher would find the `S`
 * inside `RED-JESTER RED` and store the colour as `RED-JE`.
 *
 * ## Scoping
 *
 * `withoutBuyerScope()` with an explicit `buyer_id`, for the reason
 * {@see BqsPoLinker::candidateRows()} gives: this runs from an import, which may be
 * replayed from a queue or a console command with no authenticated actor, and the
 * global scope would then filter nothing — or worse, filter differently depending on
 * who happened to be signed in.
 */
class BqsSizeVocabulary
{
    /**
     * Every size label on this buyer's current, usable BQS sheets.
     *
     * Ordered by the buyer's own column sequence: `size_order` exists because a size
     * label sorts meaninglessly as text, and `XS` after `XL` is wrong in a way no
     * reader would forgive.
     *
     * @return list<string>
     */
    public function forBuyer(?int $buyerId): array
    {
        if ($buyerId === null) {
            return [];
        }

        return BqsRowPackSize::query()
            ->whereIn('bqs_row_id', BqsRow::query()
                ->whereIn('bqs_sheet_id', BqsSheet::query()
                    ->withoutBuyerScope()
                    ->current()
                    ->usable()
                    ->where('buyer_id', $buyerId)
                    ->select('id'))
                ->select('id'))
            ->orderBy('size_order')
            ->pluck('size_label')
            ->map(static fn (string $label): string => trim($label))
            ->filter(static fn (string $label): bool => $label !== '')
            ->unique()
            ->values()
            ->all();
    }
}
