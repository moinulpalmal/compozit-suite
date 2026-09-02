<?php

namespace App\Http\Requests\Merchandising;

use App\Enums\Merchandising\BqsConflictDecision;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the uploader's decision about a BQS an import held back.
 *
 * **One decision, not a map of them.** `PurchaseOrderResolveRequest` takes an array
 * keyed by PO number because a document holds up to fifty orders that each collide on
 * their own. A workbook is one BQS: the collision is detected by its rows' keys
 * intersecting a held revision's, and it is answered once. A 200-row BQS would
 * otherwise produce a 200-decision form nobody could use.
 *
 * **Overwrite needs `delete`, not `import`.** Choosing it destroys a stored revision
 * and every row under it, and a user allowed to add a BQS is not thereby allowed to
 * destroy one — the same split that keeps `admin.users.assign-roles` apart from
 * `admin.users.update`. The dialog omits the option entirely for a user without it, so
 * this check is the backstop against a hand-made request rather than the thing the
 * user meets.
 *
 * A missing decision defaults to {@see BqsConflictDecision::Skip}: the staged rows on
 * the server decide what is applied, not the shape of the form, so a stale tab cannot
 * write a BQS that is no longer waiting.
 */
class BqsResolveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->input('decision') !== BqsConflictDecision::Overwrite->value) {
            return true;
        }

        return (bool) $this->user()?->can('merchandising.bqs.delete');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['nullable', Rule::enum(BqsConflictDecision::class)],
        ];
    }

    /**
     * The decision, defaulting to the one that changes nothing.
     */
    public function decision(): BqsConflictDecision
    {
        $decision = $this->validated()['decision'] ?? null;

        return is_string($decision)
            ? BqsConflictDecision::from($decision)
            : BqsConflictDecision::Skip;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.enum' => __('Choose whether to skip, revise or overwrite this BQS.'),
        ];
    }
}
