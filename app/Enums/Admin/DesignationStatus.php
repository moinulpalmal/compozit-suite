<?php

namespace App\Enums\Admin;

/**
 * Whether a designation may still be assigned to a user.
 *
 * Stored as the single character the HR system uses, the same shape as
 * {@see Gender}. This is deliberately *not* the boolean `users.approved`
 * spells active with, and it is not `deleted_at` either — a designation is
 * deactivated to retire it from the pickers, and deleted only when nobody
 * holds it. See documentation/admin.md.
 */
enum DesignationStatus: string
{
    case Active = 'A';
    case Inactive = 'I';

    /**
     * The label rendered in the designation form's select.
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
