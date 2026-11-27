<?php

namespace App\Modules\Platform\Http\Requests\Vendor;

use App\Modules\Platform\Policies\VendorUiKitPolicy;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation demo of the vendor UI kit form (VEN-093): shows the 422 contract on the
 * vendor-kit fields (filled input, Select2, textarea, modal form). Nothing is stored.
 */
class VendorUiKitFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('vendor')?->can(VendorUiKitPolicy::ABILITY);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_name' => ['required', 'string', 'max:120'],
            'market' => ['required', 'string', 'in:sa,ae,eg,kw'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_name' => __('platform::vendor_ui_kit.fields.product_name'),
            'market' => __('platform::vendor_ui_kit.fields.market'),
            'note' => __('platform::vendor_ui_kit.fields.note'),
        ];
    }
}
