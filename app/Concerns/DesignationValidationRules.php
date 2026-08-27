<?php

namespace App\Concerns;

use App\Enums\Admin\DesignationStatus;
use App\Models\Admin\Designation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules the designation create and edit forms share.
 *
 * Extracted the way `EmployeeValidationRules` is, so the two requests differ
 * only in the id they ignore for uniqueness.
 */
trait DesignationValidationRules
{
    /**
     * Get the validation rules that apply to a designation.
     *
     * `short_form` is nullable but unique when present — the database says the
     * same thing, and this layer is what turns the collision into a field
     * error instead of a driver exception.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function designationRules(?int $designationId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(Designation::class, 'name')->ignore($designationId),
            ],
            'short_form' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique(Designation::class, 'short_form')->ignore($designationId),
            ],
            'status' => ['required', Rule::enum(DesignationStatus::class)],
        ];
    }

    /**
     * Messages that name the field the way the form labels it.
     *
     * @return array<string, string>
     */
    protected function designationMessages(): array
    {
        return [
            'name.unique' => __('A designation with that name already exists.'),
            'short_form.unique' => __('A designation with that short form already exists.'),
        ];
    }
}
