<?php

namespace App\Http\Requests;

use App\Enums\FilterType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query string behind an index screen.
 *
 * Every Admin list is paginated, sortable, and filtered per column
 * (ARCHITECTURE.md §8.6); this is the request half of that, `App\Concerns\Listable`
 * the model half. Subclasses name their model's allow-lists and add whatever
 * else that surface has of its own.
 *
 * The wire format is `?filter[name]=man&sort=name&direction=asc&per_page=50&page=2`.
 * A filter key outside the model's `FILTERABLE` map is a validation **error**
 * rather than a silent ignore — the same guarantee the old `search_field`
 * allow-list gave.
 *
 * Sort column and filter keys are checked here **and** in the model scopes.
 * Validating twice is deliberate: this layer gives the user a clear error, and
 * the scope guarantees nothing unexpected reaches `orderBy()` or `where()` even
 * if the list is called from somewhere else.
 *
 * It lives at the root of `app/Http/Requests/` rather than in a module
 * directory, because it belongs to no module — a documented exception to
 * ARCHITECTURE.md §6.1.
 */
abstract class ListRequest extends FormRequest
{
    /**
     * The page sizes a list will serve.
     *
     * Clamped to an allow-list rather than merely capped: an unvalidated
     * `?per_page=999999` is a denial-of-service that costs nothing to send.
     *
     * @var list<int>
     */
    public const array PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * The page size a list uses when none is chosen.
     */
    public const int DEFAULT_PER_PAGE = 10;

    /**
     * The columns this surface may be sorted by.
     *
     * @return list<string>
     */
    abstract protected function sortable(): array;

    /**
     * The columns this surface's filter row exposes, and how each one matches.
     *
     * @return array<string, FilterType>
     */
    abstract protected function filterable(): array;

    /**
     * Rules for anything this surface adds beyond the filter row.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [];
    }

    /**
     * Values for what this surface adds beyond the filter row, with defaults.
     *
     * @return array<string, string>
     */
    protected function filterValues(): array
    {
        return [];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sort' => ['sometimes', Rule::in($this->sortable())],
            'direction' => ['sometimes', 'in:asc,desc'],
            'per_page' => ['sometimes', Rule::in(self::PER_PAGE_OPTIONS)],
            /*
             * `array:` with an explicit key list is what rejects an unknown
             * filter column. Without the key list, `filter[password]=x` would
             * validate and then be dropped by the scope — an unhelpful silence.
             */
            'filter' => ['sometimes', 'array:'.implode(',', array_keys($this->filterable()))],
            'filter.*' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            ...$this->filterRules(),
        ];
    }

    /**
     * The list's filter state, with every default applied.
     *
     * Every filterable column is present whether or not it was submitted, so
     * the front end can render the row from this alone and the shape does not
     * change between a filtered and an unfiltered visit.
     *
     * @return array{sort: string, direction: string, per_page: int, filter: array<string, string>}
     */
    public function filters(): array
    {
        $sortable = $this->sortable();
        $submitted = $this->array('filter');

        $filter = [];

        foreach (array_keys($this->filterable()) as $column) {
            $value = $submitted[$column] ?? '';

            $filter[$column] = is_scalar($value) ? trim((string) $value) : '';
        }

        return [
            'sort' => $this->filled('sort') ? $this->string('sort')->value() : ($sortable[0] ?? 'id'),
            'direction' => $this->string('direction')->value() === 'desc' ? 'desc' : 'asc',
            'per_page' => $this->integer('per_page') ?: self::DEFAULT_PER_PAGE,
            'filter' => $filter,
            ...$this->filterValues(),
        ];
    }
}
