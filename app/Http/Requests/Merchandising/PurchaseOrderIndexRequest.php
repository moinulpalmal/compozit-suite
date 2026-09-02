<?php

namespace App\Http\Requests\Merchandising;

use App\Enums\Merchandising\PoParseStatus;
use App\Http\Requests\ListRequest;
use App\Models\Merchandising\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the purchase-order list's query string.
 *
 * Everything but `view` and the status rule comes from `ListRequest`; see
 * ARCHITECTURE.md §8.6.
 *
 * **`view` is a toolbar control, not a filter cell.** It chooses which record *set*
 * the list is over — the current revisions, an order's full revision history, or the
 * imports that failed to parse — which §8.6 distinguishes from a filter that narrows
 * one. It is also why it is `view` and not `filter[view]`: a scalar and an array
 * cannot share one query-string key. The users list's `?view=active|trashed` is the
 * pattern this follows.
 */
class PurchaseOrderIndexRequest extends ListRequest
{
    /** The record set the list is over. */
    public const string VIEW_CURRENT = 'current';

    /** Every revision of every order, not only the newest. */
    public const string VIEW_REVISIONS = 'revisions';

    /** Only orders whose parse failed — kept for diagnosis, not for use. */
    public const string VIEW_FAILED = 'failed';

    /** @var list<string> */
    public const array VIEWS = [self::VIEW_CURRENT, self::VIEW_REVISIONS, self::VIEW_FAILED];

    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return PurchaseOrder::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return PurchaseOrder::FILTERABLE;
    }

    /**
     * {@inheritDoc}
     *
     * The status cells are dropdowns, so a value outside the enum is a malformed
     * request rather than a filter that finds nothing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'view' => ['sometimes', Rule::in(self::VIEWS)],
            'filter.parse_status' => ['nullable', Rule::enum(PoParseStatus::class)],
        ];
    }

    /**
     * The record set this visit is over, defaulting to the current revisions.
     *
     * A typed accessor rather than a read of `filters()['view']`: the base class
     * declares that array's shape and a subclass's extras are not part of it, so
     * reaching into it for `view` is an offset static analysis cannot verify.
     */
    public function view(): string
    {
        $view = $this->string('view')->value();

        return in_array($view, self::VIEWS, true) ? $view : self::VIEW_CURRENT;
    }

    /**
     * {@inheritDoc}
     *
     * The front end still receives `view` in the `filters` prop, so the toolbar
     * tabs render their active state from the same value the query ran with.
     *
     * @return array<string, string>
     */
    protected function filterValues(): array
    {
        return ['view' => $this->view()];
    }
}
