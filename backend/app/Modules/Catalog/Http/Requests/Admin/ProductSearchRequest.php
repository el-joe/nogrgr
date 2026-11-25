<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Product search of the assign modal: ?q=…&page=…
 */
class ProductSearchRequest extends FormRequest
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
        return $this->filled('q') ? (string) $this->validated('q') : null;
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
