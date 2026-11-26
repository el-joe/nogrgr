<?php

namespace App\Modules\Notifications\Http\Requests\Vendor;

use App\Modules\Notifications\DTOs\VendorNotificationPreferencesData;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\VendorNotificationTopic;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT preferences[<topic>][<channel>] = 0|1 for every topic × channel (VEN-087);
 * unchecked boxes send 0 through their hidden input.
 */
class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['preferences' => ['required', 'array:'.implode(',', VendorNotificationTopic::values())]];

        foreach (VendorNotificationTopic::cases() as $topic) {
            $rules['preferences.'.$topic->value] = ['required', 'array:'.implode(',', NotificationChannel::values())];

            foreach (NotificationChannel::cases() as $channel) {
                $rules['preferences.'.$topic->value.'.'.$channel->value] = ['required', 'boolean'];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (VendorNotificationTopic::cases() as $topic) {
            foreach (NotificationChannel::cases() as $channel) {
                $attributes['preferences.'.$topic->value.'.'.$channel->value] = $topic->label().' — '.$channel->label();
            }
        }

        return $attributes;
    }

    public function toData(): VendorNotificationPreferencesData
    {
        $matrix = [];

        foreach (VendorNotificationTopic::cases() as $topic) {
            foreach (NotificationChannel::cases() as $channel) {
                $matrix[$topic->value][$channel->value] = $this->boolean('preferences.'.$topic->value.'.'.$channel->value);
            }
        }

        return new VendorNotificationPreferencesData($matrix);
    }
}
