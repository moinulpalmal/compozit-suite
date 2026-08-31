<?php

namespace App\Http\Requests\Admin;

use App\Concerns\BuyerValidationRules;
use App\Models\Admin\Buyer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BuyerUpdateRequest extends FormRequest
{
    use BuyerValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $buyer = $this->route('buyer');

        return $this->buyerRules($buyer instanceof Buyer ? $buyer->id : null);
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->buyerMessages();
    }
}
