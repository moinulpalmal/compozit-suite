<?php

namespace App\Http\Requests\Settings;

use App\Concerns\NotificationColorValidationRules;
use App\Models\Settings\NotificationColor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NotificationColorUpdateRequest extends FormRequest
{
    use NotificationColorValidationRules;

    /**
     * Uppercase the colour before the unique rule compares it.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeColorCode();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $notificationColor = $this->route('notification_color');

        return $this->notificationColorRules(
            $notificationColor instanceof NotificationColor ? $notificationColor->id : null,
        );
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->notificationColorMessages();
    }
}
