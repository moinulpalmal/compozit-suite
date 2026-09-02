<?php

namespace App\Http\Requests\Merchandising;

use App\Enums\Merchandising\PoConflictDecision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the uploader's decision about each purchase order an import held back.
 *
 * **Overwrite needs `delete`, not `import`.** Choosing it destroys a stored order, and
 * a user allowed to add orders is not thereby allowed to destroy them — the same split
 * that keeps `admin.users.assign-roles` apart from `admin.users.update`. The dialog
 * omits the option entirely for a user without it, so this check is the backstop
 * against a hand-made request rather than the thing the user meets.
 *
 * Everything else is deliberately permissive. An unknown PO number is ignored rather
 * than rejected, and a missing decision defaults to
 * {@see PoConflictDecision::Skip} — the staged orders on the server decide what is
 * applied, not the shape of the form, so a stale tab cannot write an order that is no
 * longer waiting.
 */
class PurchaseOrderResolveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->wantsToOverwrite()) {
            return true;
        }

        return (bool) $this->user()?->can('merchandising.purchase-orders.delete');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['nullable', 'array'],
            'decisions.*' => [Rule::enum(PoConflictDecision::class)],
        ];
    }

    /**
     * The decisions, as enum cases keyed by purchase-order number.
     *
     * @return array<string, PoConflictDecision>
     */
    public function decisions(): array
    {
        /** @var array<string, string> $submitted */
        $submitted = $this->validated()['decisions'] ?? [];

        return array_map(
            fn (string $decision): PoConflictDecision => PoConflictDecision::from($decision),
            $submitted,
        );
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decisions.*.enum' => __('Choose whether to skip, revise or overwrite each purchase order.'),
        ];
    }

    /**
     * Whether any order was marked to be overwritten.
     *
     * Reads the raw input: `authorize()` runs before validation, so nothing here may
     * assume the payload is well formed.
     */
    private function wantsToOverwrite(): bool
    {
        $decisions = $this->input('decisions');

        if (! is_array($decisions)) {
            return false;
        }

        return in_array(PoConflictDecision::Overwrite->value, $decisions, strict: true);
    }
}
