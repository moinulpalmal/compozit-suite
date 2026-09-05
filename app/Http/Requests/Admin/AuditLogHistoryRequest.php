<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a request for one record's audit history.
 *
 * **The client names a record by morph alias and id — never by class.** The alias
 * is checked against the morph map before it reaches a query, so a supplied
 * string can only ever be one of the values the application itself writes into
 * `auditable_type`. This is the one rule worth carrying over verbatim from the
 * implementation this was ported from, where the same guard is spelled out in a
 * comment above its allow-list.
 *
 * Authorization is the route's `permission:admin.audit-logs.view` middleware; the
 * trail is not buyer-scoped because only a super admin can reach it at all
 * (ARCHITECTURE.md §9.3).
 */
class AuditLogHistoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(Relation::morphMap()))],
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * The morph alias of the record whose history was asked for.
     */
    public function type(): string
    {
        return $this->string('type')->value();
    }

    /**
     * The id of the record whose history was asked for.
     */
    public function recordId(): int
    {
        return $this->integer('id');
    }
}
