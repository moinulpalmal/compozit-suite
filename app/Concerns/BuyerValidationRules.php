<?php

namespace App\Concerns;

use App\Enums\RecordStatus;
use App\Models\Admin\Buyer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules the buyer create and edit forms share.
 *
 * Extracted the way `DesignationValidationRules` is, so the two requests differ
 * only in the id they ignore for uniqueness.
 */
trait BuyerValidationRules
{
    /**
     * Get the validation rules that apply to a buyer.
     *
     * `code` is nullable but unique when present — the database says the same
     * thing, and this layer is what turns the collision into a field error
     * instead of a driver exception. Nullable because rows carried over from the
     * old system arrive without one.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function buyerRules(?int $buyerId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique(Buyer::class, 'name')->ignore($buyerId),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique(Buyer::class, 'code')->ignore($buyerId),
            ],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }

    /**
     * Messages that name the field the way the form labels it.
     *
     * @return array<string, string>
     */
    protected function buyerMessages(): array
    {
        return [
            'name.unique' => __('A buyer with that name already exists.'),
            'code.unique' => __('A buyer with that code already exists.'),
        ];
    }
}
