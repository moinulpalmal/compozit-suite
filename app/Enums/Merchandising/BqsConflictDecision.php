<?php

namespace App\Enums\Merchandising;

use App\Services\Merchandising\BqsImportService;

/**
 * What to do with an uploaded BQS that collides with one already held.
 *
 * The three decisions read the same as {@see PoConflictDecision}'s, and they are
 * deliberately a separate enum because what is being decided is not the same thing.
 * A purchase order collides on its own number, one order at a time, up to fifty per
 * document. A BQS has no number at all: the collision is detected by its rows' keys
 * intersecting a held revision's, and it is answered **once for the whole workbook**.
 * A shared enum would have to document both, and its `@see` could only point at one.
 *
 * If a third importer ever wants these words, that is the point at which one
 * `ImportConflictDecision` earns its place — not before.
 *
 * Applied by {@see BqsImportService::resolve()}.
 */
enum BqsConflictDecision: string
{
    /**
     * Leave the held BQS alone. The **default**, so a careless confirmation cannot
     * change anything that already exists.
     */
    case Skip = 'skip';

    /** A genuine reissue: store it as the next revision and keep what is held. */
    case Revise = 'revise';

    /**
     * Replace the current revision in place, destroying its rows. Gated on
     * `merchandising.bqs.delete` rather than `import`.
     */
    case Overwrite = 'overwrite';

    /**
     * The label rendered beside the conflict.
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
     * Whether choosing this destroys a stored BQS revision.
     */
    public function isDestructive(): bool
    {
        return $this === self::Overwrite;
    }

    /**
     * Every case as the option list the conflict step renders.
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
