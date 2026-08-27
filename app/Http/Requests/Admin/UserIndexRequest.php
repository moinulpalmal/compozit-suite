<?php

namespace App\Http\Requests\Admin;

use App\Enums\Admin\Gender;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the Admin user list's query string.
 *
 * Sort column and search field are checked against the model's allow-lists
 * here as well as in the scopes. Validating twice is deliberate: this layer
 * gives the user a clear error, and the scope guarantees nothing unexpected
 * reaches `orderBy()` even if the list is ever called from somewhere else.
 */
class UserIndexRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'in:active,trashed'],
            'sort' => ['sometimes', Rule::in(User::SORTABLE)],
            'direction' => ['sometimes', 'in:asc,desc'],
            'search_field' => ['sometimes', Rule::in(User::SEARCHABLE)],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],
            'status' => ['sometimes', 'nullable', 'in:active,inactive'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * The list's filter state, with every default applied.
     *
     * @return array{filter: string, sort: string, direction: string, search_field: string, search: string, gender: string, status: string}
     */
    public function filters(): array
    {
        return [
            'filter' => $this->string('filter')->value() === 'trashed' ? 'trashed' : 'active',
            'sort' => $this->filled('sort') ? $this->string('sort')->value() : 'name',
            'direction' => $this->string('direction')->value() === 'desc' ? 'desc' : 'asc',
            'search_field' => $this->filled('search_field') ? $this->string('search_field')->value() : 'name',
            'search' => $this->string('search')->value(),
            'gender' => $this->string('gender')->value(),
            'status' => $this->string('status')->value(),
        ];
    }
}
