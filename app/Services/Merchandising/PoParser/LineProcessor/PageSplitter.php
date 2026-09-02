<?php

namespace App\Services\Merchandising\PoParser\LineProcessor;

use App\Services\Merchandising\PoParser\Support\Page;
use App\Services\Merchandising\PoParser\Support\RegexLibrary;

/**
 * Cuts a flat list of lines into pages, using the banner each page opens with.
 *
 * Lines before the first banner are discarded rather than kept as an untitled page:
 * a Walmart document begins with one, so anything ahead of it is converter preamble
 * and belongs to no purchase order.
 */
final class PageSplitter
{
    /**
     * @param  list<array{index: int, text: string}>  $lines
     * @return list<Page>
     */
    public function split(array $lines): array
    {
        $pages = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match(RegexLibrary::PAGE_ANCHOR, $line['text'], $matches) === 1) {
                if ($current instanceof Page) {
                    $pages[] = $current;
                }

                $current = new Page($matches[1], (int) $matches[2]);
            }

            if ($current instanceof Page) {
                $current->lines[] = $line;
            }
        }

        if ($current instanceof Page) {
            $pages[] = $current;
        }

        return $pages;
    }
}
