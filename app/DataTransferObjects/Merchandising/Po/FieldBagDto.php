<?php

namespace App\DataTransferObjects\Merchandising\Po;

/**
 * A document section whose field *set* is open, held as label → value pairs.
 *
 * Three sections of a Walmart purchase order are like this: logistics, the line-item
 * header, and each tariff entry. They print whichever labels apply to that order, so
 * a fixed constructor signature would either carry dozens of permanently-null
 * properties or silently drop labels this template has not shown us yet.
 *
 * **The trade is deliberate and it has a cost:** nothing static-checks a key. Callers
 * reading these use {@see self::get()} with a default, and a key that stops appearing
 * degrades to that default rather than failing. That is the right failure mode for a
 * parser reading someone else's template, and the wrong one for anything the
 * application itself owns — which is why only these three sections use it.
 */
abstract readonly class FieldBagDto
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function __construct(
        public array $fields = [],
    ) {}

    /**
     * Read one field, falling back to `$default` when the document did not print it.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->fields[$key] ?? $default;
    }

    /**
     * Whether the document printed this field at all.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->fields);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->fields;
    }
}
