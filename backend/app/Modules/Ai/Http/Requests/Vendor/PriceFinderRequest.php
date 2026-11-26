<?php

namespace App\Modules\Ai\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * VEN-010b AI price finder: the selling country to search the market of (one of the
 * store's selling countries).
 */
class PriceFinderRequest extends FormRequest
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
            'country_id' => ['required', 'integer', Rule::in(array_map('intval', (array) (tenant()?->selling_country_ids ?? [])))],
        ];
    }
}
