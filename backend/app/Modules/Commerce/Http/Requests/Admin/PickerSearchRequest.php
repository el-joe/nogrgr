<?php

namespace App\Modules\Commerce\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Select2 remote search (product / store pickers): ?q=term&page=n.
 */
class PickerSearchRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function term(): ?string
    {
        $term = $this->validated('q');

        return is_string($term) && trim($term) !== '' ? trim($term) : null;
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
