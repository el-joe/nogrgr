<?php

namespace App\Modules\Identity\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH {is_active: 0|1} from the staff list switch / row actions (VEN-076).
 */
class StaffStatusRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function active(): bool
    {
        return $this->boolean('is_active');
    }
}
