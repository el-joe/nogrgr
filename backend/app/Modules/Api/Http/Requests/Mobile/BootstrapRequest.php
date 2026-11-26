<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use App\Modules\Notifications\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * App start-up: platform + app version (update decision) and the shopping country.
 * The values may also come as `X-App-Platform` / `X-App-Version` / `X-Country` headers.
 */
class BootstrapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'platform' => $this->input('platform', $this->header('X-App-Platform')),
            'app_version' => $this->input('app_version', $this->header('X-App-Version')),
            'country' => $this->input('country', $this->header('X-Country') ?? $this->header('X-Country-Code')),
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['sometimes', Rule::enum(DevicePlatform::class)],
            'app_version' => ['sometimes', 'string', 'max:32', 'regex:/^\d+(\.\d+){0,3}([+-][A-Za-z0-9.]+)?$/'],
            'country' => ['sometimes', 'string', 'size:2', 'alpha'],
        ];
    }
}
