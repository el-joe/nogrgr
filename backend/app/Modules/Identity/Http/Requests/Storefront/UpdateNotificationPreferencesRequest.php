<?php

namespace App\Modules\Identity\Http\Requests\Storefront;

use App\Modules\Identity\Enums\CustomerNotificationChannel;
use App\Modules\Identity\Enums\CustomerNotificationType;
use App\Modules\Identity\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Account › Notifications: `preferences[{type}][{channel}]` booleans. HTML forms post
 * only checked boxes, so a type/channel missing from a full form submit means "off"
 * (send `preferences` complete, or use JSON with explicit false values).
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('customer') instanceof Customer;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $rules = ['preferences' => ['present', 'array:'.implode(',', CustomerNotificationType::values())]];

        foreach (CustomerNotificationType::cases() as $type) {
            $rules['preferences.'.$type->value] = ['sometimes', 'array:'.implode(',', CustomerNotificationChannel::values())];

            foreach (CustomerNotificationChannel::cases() as $channel) {
                $rules['preferences.'.$type->value.'.'.$channel->value] = ['sometimes', 'boolean'];
            }
        }

        return $rules;
    }

    /**
     * Full type => channel => bool matrix (missing = false for form posts).
     *
     * @return array<string, array<string, bool>>
     */
    public function preferences(): array
    {
        /** @var array<string, array<string, mixed>> $input */
        $input = (array) $this->validated('preferences', []);
        $matrix = [];

        foreach (CustomerNotificationType::cases() as $type) {
            foreach (CustomerNotificationChannel::cases() as $channel) {
                $value = $input[$type->value][$channel->value] ?? null;

                if ($value === null && $this->expectsJson()) {
                    continue;
                }

                $matrix[$type->value][$channel->value] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $matrix;
    }
}
