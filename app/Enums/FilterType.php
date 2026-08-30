<?php

namespace App\Enums;

/**
 * How one column's filter cell matches.
 *
 * Every Admin list has a filter row under its headers, and each model declares
 * what each of its columns does there via `FILTERABLE` (ARCHITECTURE.md §8.6).
 * The choice is **declared per column, never inferred from the column type** —
 * every filterable column in this application is a `varchar`, including
 * `employee_id` and the phone numbers, so type inference would make everything
 * `Contains` and quietly retire several measured indexes.
 *
 * The trade-off, stated once:
 *
 * - {@see self::Contains} finds mid-string ("868" finds 15868) and **cannot use
 *   an index**, at any selectivity. Right for names and emails.
 * - {@see self::Prefix} is indexable and is what keeps
 *   `users_deleted_at_personal_mobile_index` and its siblings earning their
 *   write cost. Right for identifiers.
 *
 * At the root of `app/Enums/` beside {@see RecordStatus} because it belongs to
 * no module.
 */
enum FilterType: string
{
    /** `LIKE '%term%'` — finds mid-string, never uses an index. */
    case Contains = 'contains';

    /** `LIKE 'term%'` — indexable; "868" will not find 15868. */
    case Prefix = 'prefix';

    /** `= value` — for the columns whose cell is a dropdown. */
    case Equals = 'equals';

    /**
     * Hands the value to the model's scope of the same name.
     *
     * For a cell that filters on something which is not a column of its own —
     * `Permission::scopeModule()` derives the module from the `name` prefix.
     */
    case Scope = 'scope';
}
