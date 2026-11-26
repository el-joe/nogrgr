<?php

namespace App\Modules\Platform\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * City options of the compliance center (Select2): the typed term `q` and the Select2 page.
 * The country comes from the route and must be an active central country (controller).
 */
class ComplianceCitiesRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function term(): ?string
    {
        $term = $this->validated('q');

        return is_string($term) ? $term : null;
    }
}
