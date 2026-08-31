<?php

namespace App\Concerns;

use App\Enums\FilterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Column filtering and allow-listed sorting for a model behind an index screen.
 *
 * Every Admin list is paginated, sortable, and filtered by a row of cells under
 * its headers (ARCHITECTURE.md §8.6). This is the model half of that; the
 * request half is `App\Http\Requests\ListRequest`.
 *
 * A using model **must** declare both allow-lists:
 *
 * ```php
 * public const array FILTERABLE = [
 *     'name'   => FilterType::Contains,
 *     'status' => FilterType::Equals,
 * ];
 *
 * public const array SORTABLE = ['name', 'created_at'];
 * ```
 *
 * They stay on the model rather than in this trait for two reasons: PHP will not
 * let a class override a constant a trait defines, and controllers ship them to
 * the front end (`User::SORTABLE`) so the table knows which headers to make
 * clickable and which cell to render in each filter column.
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
     * Apply the filter row's values, each column matching the way it declares.
     *
     * The allow-list is load-bearing twice over: a key absent from `FILTERABLE`
     * never reaches the query at all, and the match type comes from the model
     * rather than from the request, so no caller can turn an indexed prefix
     * lookup into a table scan by changing a query parameter.
     *
     * Blank values are skipped rather than matched, so an empty cell means "no
     * filter" instead of "rows whose name is the empty string".
     *
     * @param  Builder<static>  $query
     * @param  array<string, string>  $filters
     */
    public function scopeFilterColumns(Builder $query, array $filters): void
    {
        foreach (static::FILTERABLE as $column => $type) {
            $value = trim((string) ($filters[$column] ?? ''));

            if ($value === '') {
                continue;
            }

            match ($type) {
                FilterType::Contains => $query->where($column, 'like', '%'.static::escapeLike($value).'%'),
                FilterType::Prefix => $query->where($column, 'like', static::escapeLike($value).'%'),
                FilterType::Equals => $query->where($column, $value),
                FilterType::Scope => $query->{Str::camel($column)}($value),
            };
        }
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

    /**
     * Neutralise the wildcards a user can type into a `LIKE` term.
     *
     * Without this a term of "%" matches every row instead of none, and "_"
     * silently becomes a single-character wildcard.
     */
    protected static function escapeLike(string $term): string
    {
        return addcslashes($term, '%_\\');
    }
}
