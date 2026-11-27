<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use App\Modules\Api\Http\Requests\Mobile\Concerns\ValidatesDeviceId;
use App\Modules\Identity\Enums\SocialProvider;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Mobile "Sign in with Google / Apple": the provider id_token (+ the raw nonce the app
 * sent to Apple, the name Apple returns only on the first sign-in, the app install id).
 */
class MobileSocialLoginRequest extends FormRequest
{
    use ValidatesDeviceId;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string', 'max:8192'],
            'nonce' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            ...$this->deviceIdRules(),
        ];
    }

    public function provider(): SocialProvider
    {
        return SocialProvider::from((string) $this->route('provider'));
    }
}
