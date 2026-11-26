<?php

namespace App\Modules\Api\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Paginated Mobile API lists: `page` + `per_page` (1–50, default 20).
 */
class PageRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 20;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::DEFAULT_PER_PAGE);
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
