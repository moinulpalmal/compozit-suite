<?php

namespace App\Enums\Merchandising;

use App\Services\Merchandising\PurchaseOrderImportService;

/**
 * What to do with an imported purchase order that collides with one already held.
 *
 * The parser cannot tell a genuine Walmart reissue from someone re-uploading a stale
 * document — both arrive as the same order number with different content. Only the
 * person who uploaded it knows, so they are asked, one order at a time. See
 * [`documentation/merchandising.md`](../../../documentation/merchandising.md) §3.5.
 *
 * Applied by {@see PurchaseOrderImportService::resolve()}.
 */
enum PoConflictDecision: string
{
    /**
     * Leave the held order alone. The **default**, so a careless confirmation cannot
     * change anything that already exists.
     */
    case Skip = 'skip';

    /** A genuine reissue: store it as the next revision and keep what is held. */
    case Revise = 'revise';

    /**
     * Replace the current revision in place. Destructive, and gated on
     * `merchandising.purchase-orders.delete` rather than `import`.
     */
    case Overwrite = 'overwrite';

    /**
     * The label rendered beside each conflict.
     */
    public function label(): string
    {
        return match ($this) {
            self::Skip => __('Skip'),
            self::Revise => __('Revise'),
            self::Overwrite => __('Overwrite'),
        };
    }

    /**
     * Whether choosing this destroys a stored order.
     */
    public function isDestructive(): bool
    {
        return $this === self::Overwrite;
    }

    /**
     * Every case as the option list the conflict rows render.
     *
     * @return list<array{value: string, label: string, destructive: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $decision): array => [
                'value' => $decision->value,
                'label' => $decision->label(),
                'destructive' => $decision->isDestructive(),
            ],
            self::cases(),
        );
    }
}
