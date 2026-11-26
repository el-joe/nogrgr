<?php

namespace App\Modules\Ai\Http\Requests\Vendor;

use App\Modules\Ai\Enums\SocialPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-010c AI social posts: platform + one of the store's enabled languages (checked
 * against the store languages by the controller).
 */
class SocialPostRequest extends FormRequest
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
            'platform' => ['required', Rule::enum(SocialPlatform::class)],
            'locale' => ['required', 'string', 'regex:/^[a-z]{2,3}([_-][A-Za-z]{2,4})?$/'],
        ];
    }

    public function platform(): SocialPlatform
    {
        return SocialPlatform::from((string) $this->validated('platform'));
    }
}
