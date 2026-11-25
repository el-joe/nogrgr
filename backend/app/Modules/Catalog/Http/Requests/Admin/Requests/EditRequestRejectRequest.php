<?php

namespace App\Modules\Catalog\Http\Requests\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rejecting a product edit request needs a reason (shown to the vendor).
 */
class EditRequestRejectRequest extends FormRequest
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
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => (string) __('catalog::requests.edits.reason')];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
