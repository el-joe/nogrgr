<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use App\Modules\Notifications\DTOs\CustomerDeviceData;
use App\Modules\Notifications\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Register / refresh an app install and its FCM token (guest or signed-in).
 */
class RegisterDeviceRequest extends FormRequest
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
        return [
            'device_id' => ['required', 'string', 'min:8', 'max:100', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'push_token' => ['nullable', 'string', 'max:512'],
            'push_enabled' => ['sometimes', 'boolean'],
            'app_version' => ['nullable', 'string', 'max:32', 'regex:/^\d+(\.\d+){0,3}([+-][A-Za-z0-9.]+)?$/'],
            'os_version' => ['nullable', 'string', 'max:32'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toData(): CustomerDeviceData
    {
        return new CustomerDeviceData(
            deviceId: (string) $this->validated('device_id'),
            platform: DevicePlatform::from((string) $this->validated('platform')),
            pushToken: $this->filled('push_token') ? (string) $this->validated('push_token') : null,
            pushEnabled: $this->boolean('push_enabled', true),
            appVersion: $this->validated('app_version'),
            osVersion: $this->validated('os_version'),
            model: $this->validated('model'),
            locale: app()->getLocale(),
        );
    }
}
