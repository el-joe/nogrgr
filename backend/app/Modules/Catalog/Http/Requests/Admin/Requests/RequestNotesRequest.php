<?php

namespace App\Modules\Catalog\Http\Requests\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Internal (admin-only) notes of a manufacturing / brand request.
 */
class RequestNotesRequest extends FormRequest
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
        return ['admin_notes' => ['nullable', 'string', 'max:5000']];
    }

    public function notes(): ?string
    {
        $notes = $this->validated('admin_notes');

        return is_string($notes) ? $notes : null;
    }
}
