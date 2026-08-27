<?php

namespace App\Enums;

use App\Concerns\HasStatus;

/**
 * Whether a record is in active use.
 *
 * The application's one active/inactive vocabulary, stored as the single
 * character the HR system uses. Applied through {@see HasStatus},
 * which supplies the cast and the scopes so a model does not re-declare them.
 *
 * It lives at the root of `app/Enums/` rather than a module folder because it
 * belongs to no module — the same reasoning as {@see Theme}.
 *
 * **This is not a workflow status.** A BQS or purchase order moving through
 * Draft → Approved → Cancelled is a different concept with a different
 * lifecycle, and belongs in its own module-scoped enum. This one is named
 * `RecordStatus` rather than `Status` precisely so that enum has somewhere
 * obvious to go that is not here.
 */
enum RecordStatus: string
{
    case Active = 'A';
    case Inactive = 'I';

    /**
     * The label rendered in a status select.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Inactive => __('Inactive'),
        };
    }

    /**
     * Every case as the option list the front end renders.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
