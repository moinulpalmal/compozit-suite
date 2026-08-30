<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Buyer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a change to one user's buyer access.
 *
 * Shape only. *Whether the actor may make this change* — editing their own
 * access, or granting more than they hold — is `Admin\BuyerAccessService::assignmentBlocker()`,
 * because those refusals depend on the actor and would be bypassed by
 * `Gate::before` in a policy. See ARCHITECTURE.md §9.2.
 */
class UserBuyerAccessRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'all_buyer_access' => ['sometimes', 'boolean'],
            'buyers' => ['sometimes', 'array'],
            'buyers.*' => ['integer', Rule::exists(Buyer::class, 'id')],
        ];
    }

    /**
     * Whether every buyer was granted.
     */
    public function grantsAllBuyers(): bool
    {
        return $this->boolean('all_buyer_access');
    }

    /**
     * The submitted buyer ids.
     *
     * Empty when all-access was granted — the service detaches the pivot in that
     * case regardless, so the two can never disagree.
     *
     * @return list<int>
     */
    public function submittedBuyers(): array
    {
        return array_values(array_unique(array_map(
            intval(...),
            array_filter($this->array('buyers'), is_numeric(...)),
        )));
    }
}
