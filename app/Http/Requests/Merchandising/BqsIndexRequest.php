<?php

namespace App\Http\Requests\Merchandising;

use App\Enums\Merchandising\BqsParseStatus;
use App\Http\Requests\ListRequest;
use App\Models\Merchandising\BqsSheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the BQS list's query string.
 *
 * Everything but `view` and the status rule comes from `ListRequest`; see
 * ARCHITECTURE.md §8.6.
 *
 * **`view` is a toolbar control, not a filter cell.** It chooses which record *set* the
 * list is over — the current revisions, or every revision — which §8.6 distinguishes
 * from a filter that narrows one. It is `view` rather than `filter[view]` because a
 * scalar and an array cannot share one query-string key.
 *
 * There is no `failed` view here, unlike the purchase-order list. A BQS workbook that
 * cannot be read is refused before a sheet exists, so there is no failed *sheet* to
 * look at — the diagnosis lives on `bqs_imports`, which the detail page shows.
 */
class BqsIndexRequest extends ListRequest
{
    /** Only the newest revision of each BQS. */
    public const string VIEW_CURRENT = 'current';

    /** Every revision, not only the newest. */
    public const string VIEW_REVISIONS = 'revisions';

    /** @var list<string> */
    public const array VIEWS = [self::VIEW_CURRENT, self::VIEW_REVISIONS];

    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return BqsSheet::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return BqsSheet::FILTERABLE;
    }

    /**
     * {@inheritDoc}
     *
     * The status cell is a dropdown, so a value outside the enum is a malformed
     * request rather than a filter that finds nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'view' => ['sometimes', Rule::in(self::VIEWS)],
            'filter.parse_status' => ['nullable', Rule::enum(BqsParseStatus::class)],
        ];
    }

    /**
     * The record set this visit is over, defaulting to the current revisions.
     *
     * A typed accessor rather than a read of `filters()['view']`: the base class
     * declares that array's shape and a subclass's extras are not part of it.
     */
    public function view(): string
    {
        $view = $this->string('view')->value();

        return in_array($view, self::VIEWS, true) ? $view : self::VIEW_CURRENT;
    }

    /**
     * {@inheritDoc}
     *
     * The front end still receives `view` in the `filters` prop, so the toolbar tabs
     * render their active state from the same value the query ran with.
     *
     * @return array<string, string>
     */
    protected function filterValues(): array
    {
        return ['view' => $this->view()];
    }
}
