<?php

namespace App\Http\Requests\Admin;

use App\Enums\Admin\AuditEvent;
use App\Http\Requests\ListRequest;
use App\Models\Admin\AuditLog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;

/**
 * Validates the audit list's query string.
 *
 * Two cells are dropdowns and both are allow-listed against something the
 * application already owns, rather than against a list written out again here:
 * the events come from {@see AuditEvent} and the model types from the morph map.
 * A hand-kept third copy is what drifted in the implementation this was ported
 * from — 32 models audited, 18 offered.
 */
class AuditLogIndexRequest extends ListRequest
{
    /**
     * {@inheritDoc}
     */
    protected function sortable(): array
    {
        return AuditLog::SORTABLE;
    }

    /**
     * {@inheritDoc}
     */
    protected function filterable(): array
    {
        return AuditLog::FILTERABLE;
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function filterRules(): array
    {
        return [
            'filter.event' => ['nullable', Rule::enum(AuditEvent::class)],
            /*
             * The stored value is a morph alias, so the allow-list is the map's
             * keys. A class name arriving here is either a stale bookmark or
             * somebody probing, and is a validation error either way — the column
             * has held aliases since the table was created.
             */
            'filter.auditable_type' => ['nullable', Rule::in(array_keys(Relation::morphMap()))],
        ];
    }

    /**
     * {@inheritDoc}
     *
     * **Newest first**, which inverts the base class's `asc` default for this one
     * surface. A log is read from the top: the question is almost always "what
     * happened recently", and the oldest row in a table that is never pruned is
     * the least interesting thing it holds. The column itself comes from
     * {@see AuditLog::defaultSort()}; direction cannot be expressed there.
     *
     * @return array<string, string>
     */
    protected function filterValues(): array
    {
        return [
            'direction' => $this->string('direction')->value() === 'asc' ? 'asc' : 'desc',
        ];
    }
}
