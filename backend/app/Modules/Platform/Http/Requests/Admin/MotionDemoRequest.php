<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\Support\MotionDemoSamples;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Storefront motion gallery options: ?family=ecommet|elora|souqify&dir=ltr|rtl&product=<slug>.
 */
class MotionDemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'family' => ['nullable', Rule::in(MotionDemoSamples::FAMILIES)],
            'dir' => ['nullable', Rule::in(['ltr', 'rtl'])],
            'product' => ['nullable', 'string', 'alpha_dash', 'max:40'],
        ];
    }

    public function family(): string
    {
        return (string) ($this->validated('family') ?? 'elora');
    }

    public function direction(): ?string
    {
        $direction = $this->validated('dir');

        return is_string($direction) ? $direction : null;
    }
}
