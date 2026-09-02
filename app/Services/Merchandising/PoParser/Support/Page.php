<?php

namespace App\Services\Merchandising\PoParser\Support;

use App\Services\Merchandising\PoParser\LineProcessor\PageSplitter;
use App\Services\Merchandising\PoParser\ParserService;

/**
 * One printed page of a purchase-order document, and the lines on it.
 *
 * A single uploaded file routinely holds several purchase orders across many
 * pages, so the page — not the file — is what carries a PO number. Grouping pages
 * by that number is how {@see ParserService}
 * separates the orders.
 *
 * `$lines` is appended to after construction by
 * {@see PageSplitter}, which is
 * why this is not `readonly`.
 */
final class Page
{
    /**
     * The lines belonging to this page, in document order.
     *
     * @var list<array{index: int, text: string}>
     */
    public array $lines = [];

    public function __construct(
        public readonly string $poNumber,
        public readonly int $pageNumber,
    ) {}
}
