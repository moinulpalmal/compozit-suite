<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query string behind an index screen.
 *
 * Every Admin list is paginated, searchable and sortable (ARCHITECTURE.md §8.6);
 * this is the request half of that, `App\Concerns\Listable` the model half.
 * Subclasses name their model's allow-lists and add whatever filters that
 * surface has of its own.
 *
 * Sort column and search field are checked here **and** in the model scopes.
 * Validating twice is deliberate: this layer gives the user a clear error, and
 * the scope guarantees nothing unexpected reaches `orderBy()` even if the list
 * is called from somewhere else.
 *
 * It lives at the root of `app/Http/Requests/` rather than in a module
 * directory, because it belongs to no module — a documented exception to
 * ARCHITECTURE.md §6.1.
 */
abstract class ListRequest extends FormRequest
{
    /**
     * The columns this surface may be sorted by.
     *
     * @return list<string>
     */
    abstract protected function sortable(): array;

    /**
     * The fields this surface may be searched in.
     *
     * @return list<string>
     */
    abstract protected function searchable(): array;

    /**
     * Rules for the filters this surface adds to the shared set.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [];
    }

    /**
     * Values for the filters this surface adds, with defaults applied.
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
            'search_field' => ['sometimes', Rule::in($this->searchable())],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            ...$this->filterRules(),
        ];
    }

    /**
     * The list's filter state, with every default applied.
     *
     * Returned as a flat array of strings so it round-trips through the query
     * string and back into the front end's filter object unchanged.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        $sortable = $this->sortable();
        $searchable = $this->searchable();

        return [
            'sort' => $this->filled('sort') ? $this->string('sort')->value() : ($sortable[0] ?? 'id'),
            'direction' => $this->string('direction')->value() === 'desc' ? 'desc' : 'asc',
            'search_field' => $this->filled('search_field')
                ? $this->string('search_field')->value()
                : ($searchable[0] ?? 'name'),
            'search' => $this->string('search')->value(),
            ...$this->filterValues(),
        ];
    }
}
