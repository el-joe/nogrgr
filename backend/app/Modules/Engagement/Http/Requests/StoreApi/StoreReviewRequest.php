<?php

namespace App\Modules\Engagement\Http\Requests\StoreApi;

use App\Modules\Engagement\DTOs\ReviewInputData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Write a review (SF-ECO-019a / 028d): rating 1–5, optional title, comment.
 */
class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'body'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim(strip_tags((string) $this->input($field)))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rating' => __st('reviews.fields.rating'),
            'title' => __st('reviews.fields.title'),
            'body' => __st('reviews.fields.body'),
        ];
    }

    public function toData(): ReviewInputData
    {
        $title = $this->validated('title');

        return new ReviewInputData(
            rating: (int) $this->validated('rating'),
            comment: (string) $this->validated('body'),
            title: is_string($title) ? $title : null,
            locale: app()->getLocale(),
        );
    }
}
