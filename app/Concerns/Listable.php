<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Prefix search and allow-listed sorting for a model behind an index screen.
 *
 * Extracted from `App\Models\User` when designations, roles and permissions all
 * needed the same two scopes. Every Admin list is paginated, searchable and
 * sortable (ARCHITECTURE.md §8.6), and this is the model half of that; the
 * request half is `App\Http\Requests\ListRequest`.
 *
 * A using model **must** declare both allow-lists:
 *
 * ```php
 * public const array SEARCHABLE = ['name'];
 * public const array SORTABLE = ['name', 'created_at'];
 * ```
 *
 * They stay on the model rather than in this trait for two reasons: PHP will not
 * let a class override a constant a trait defines, and controllers ship them to
 * the front end (`User::SORTABLE`) so the table knows which headers to make
 * clickable.
 */
trait Listable
{
    /**
     * The column a list falls back to when none is chosen.
     *
     * Override on a model whose natural order is not by name.
     */
    public static function defaultSort(): string
    {
        return static::SORTABLE[0] ?? 'id';
    }

    /**
     * Filter by a prefix match on one searchable field.
     *
     * **Prefix, not contains.** `LIKE 'term%'` uses an index; `LIKE '%term%'`
     * cannot, ever. So "158" finds employee 15868 but "868" does not — that is
     * the contract, not a bug. See ARCHITECTURE.md §6.3.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSearch(Builder $query, ?string $field, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '' || ! in_array($field, static::SEARCHABLE, true)) {
            return;
        }

        // Otherwise a term of "%" becomes a wildcard and scans the whole table.
        $escaped = addcslashes($term, '%_\\');

        $query->where($field, 'like', "{$escaped}%");
    }

    /**
     * Order the list by an allow-listed column.
     *
     * The allow-list is load-bearing: passing request input straight to
     * `orderBy()` is a SQL injection. An unknown column falls back to the
     * default rather than reaching the database.
     *
     * @param  Builder<static>  $query
     */
    public function scopeSortBy(Builder $query, ?string $column, ?string $direction): void
    {
        $column = in_array($column, static::SORTABLE, true) ? $column : static::defaultSort();
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $query->orderBy($column, $direction);
    }
}
