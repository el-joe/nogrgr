<?php

namespace App\Modules\Engagement\Http\Requests\StoreApi;

use App\Modules\Engagement\Enums\ReviewSort;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Product reviews list (SF-ECO-019a): page ("load more"), sort, star filter.
 */
class ReviewIndexRequest extends FormRequest
{
    public const PER_PAGE = 10;

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
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', Rule::enum(ReviewSort::class)],
            'rating' => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    public function sort(): ReviewSort
    {
        return ReviewSort::tryFrom((string) $this->validated('sort')) ?? ReviewSort::Newest;
    }

    public function rating(): ?int
    {
        $rating = $this->validated('rating');

        return $rating !== null ? (int) $rating : null;
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? self::PER_PAGE);
    }
}
