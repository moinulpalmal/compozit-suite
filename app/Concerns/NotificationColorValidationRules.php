<?php

namespace App\Concerns;

use App\Enums\RecordStatus;
use App\Models\Settings\NotificationColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules the notification colour create and edit forms share.
 *
 * Extracted the way {@see DesignationValidationRules} is, so the two requests
 * differ only in the id they ignore for uniqueness.
 */
trait NotificationColorValidationRules
{
    /**
     * Normalise the submitted colour before anything validates it.
     *
     * `#RRGGBB` is stored uppercase, and this is where it becomes uppercase —
     * **not** in a model mutator. A mutator runs after validation, so
     * `Rule::unique` would compare the raw `#ff0000` against a stored
     * `#FF0000`, pass, and hand the collision to the driver as a 500. Doing it
     * here means the unique rule and the database see the same string.
     *
     * The `#` is added when it is missing so a pasted `FF0000` is accepted; the
     * regex still refuses anything that is not six hex digits.
     */
    protected function normalizeColorCode(): void
    {
        $colorCode = $this->input('color_code');

        if (! is_string($colorCode)) {
            return;
        }

        $colorCode = strtoupper(trim($colorCode));

        if ($colorCode !== '' && ! str_starts_with($colorCode, '#')) {
            $colorCode = '#'.$colorCode;
        }

        $this->merge(['color_code' => $colorCode]);
    }

    /**
     * Get the validation rules that apply to a notification colour.
     *
     * `retention_days` is capped at ten years. There is no domain rule behind
     * the number yet — it is a sanity bound that keeps a typo out of the column
     * — and the column is an `unsignedSmallInteger`, so anything past 65535
     * would be a driver error rather than a field error either way.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function notificationColorRules(?int $notificationColorId = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique(NotificationColor::class, 'name')->ignore($notificationColorId),
            ],
            'color_code' => [
                'required',
                'string',
                'regex:/^#[0-9A-F]{6}$/',
                Rule::unique(NotificationColor::class, 'color_code')->ignore($notificationColorId),
            ],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }

    /**
     * Messages that name the field the way the form labels it.
     *
     * @return array<string, string>
     */
    protected function notificationColorMessages(): array
    {
        return [
            'name.unique' => __('A notification colour with that name already exists.'),
            'color_code.unique' => __('Another notification colour already uses that colour.'),
            'color_code.regex' => __('Enter a colour as six hex digits, for example #FF0000.'),
        ];
    }
}
