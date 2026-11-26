<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Static texts of the store in the request language, optionally one group (`mobile`,
 * `cart`, …).
 */
class TextsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['group' => ['sometimes', 'string', 'max:40', 'regex:/^[a-z_]+$/']];
    }
}
