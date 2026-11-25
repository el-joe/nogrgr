<?php

namespace App\Modules\Notifications\Http\Requests\Admin;

use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT preferences[<category>][<channel>] = 0|1 for every category × channel
 * (unchecked boxes send 0 through their hidden input).
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
        $rules = ['preferences' => ['required', 'array:'.implode(',', NotificationCategory::values())]];

        foreach (NotificationCategory::cases() as $category) {
            $rules['preferences.'.$category->value] = ['required', 'array:'.implode(',', NotificationChannel::values())];

            foreach (NotificationChannel::cases() as $channel) {
                $rules['preferences.'.$category->value.'.'.$channel->value] = ['required', 'boolean'];
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

        foreach (NotificationCategory::cases() as $category) {
            foreach (NotificationChannel::cases() as $channel) {
                $attributes['preferences.'.$category->value.'.'.$channel->value] = $category->label().' — '.$channel->label();
            }
        }

        return $attributes;
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function preferences(): array
    {
        $matrix = [];

        foreach (NotificationCategory::cases() as $category) {
            foreach (NotificationChannel::cases() as $channel) {
                $matrix[$category->value][$channel->value] = $this->boolean('preferences.'.$category->value.'.'.$channel->value);
            }
        }

        return $matrix;
    }
}
