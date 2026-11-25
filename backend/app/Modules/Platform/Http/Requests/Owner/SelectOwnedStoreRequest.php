<?php

namespace App\Modules\Platform\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Select store (OWN-002): the store to open. VendorAuthService checks the store is one
 * of the picker's candidates or owned by the signed-in owner, and active.
 */
class SelectOwnedStoreRequest extends FormRequest
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
        return ['tenant_id' => ['required', 'integer']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['tenant_id' => __('platform::owner.select.stores')];
    }
}
