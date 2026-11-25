<?php

namespace App\Modules\Platform\Http\Requests\Owner;

use App\Modules\Platform\Http\Requests\Central\StoreAddressCheck;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add another store › live store-address check (?slug=).
 */
class StoreAddressAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => StoreAddressCheck::normalizeSlug($this->query('slug'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['slug' => ['required', 'string', 'max:63']];
    }
}
