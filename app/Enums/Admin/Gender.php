<?php

namespace App\Enums\Admin;

/**
 * A user's gender, stored as the single character the HR system uses.
 */
enum Gender: string
{
    case Male = 'M';
    case Female = 'F';
    case Other = 'O';

    /**
     * The label rendered in the user form's select.
     */
    public function label(): string
    {
        return match ($this) {
            self::Male => __('Male'),
            self::Female => __('Female'),
            self::Other => __('Other'),
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
            fn (self $gender): array => ['value' => $gender->value, 'label' => $gender->label()],
            self::cases(),
        );
    }
}
