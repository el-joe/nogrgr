<?php

namespace App\Modules\Commerce\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hide / show a central coupon or flash sale in the store (VEN-040 / 041 read-only tab):
 * ?hidden=1|0.
 */
class PromotionVisibilityRequest extends FormRequest
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
        return ['hidden' => ['required', 'boolean']];
    }

    public function hidden(): bool
    {
        return $this->boolean('hidden');
    }
}
