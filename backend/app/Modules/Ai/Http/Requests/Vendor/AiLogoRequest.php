<?php

namespace App\Modules\Ai\Http\Requests\Vendor;

use App\Modules\Ai\Enums\LogoColor;
use App\Modules\Ai\Enums\LogoStyle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-034a AI logo: generate = {store_name, tagline?, style, color}; apply = {option}
 * ("<batch>/option-<n>", checked by the service).
 */
class AiLogoRequest extends FormRequest
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
        if ($this->routeIs('*.apply')) {
            return ['option' => ['required', 'string', 'max:40']];
        }

        return [
            'store_name' => ['required', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:80'],
            'style' => ['required', Rule::enum(LogoStyle::class)],
            'color' => ['required', Rule::enum(LogoColor::class)],
        ];
    }
}
