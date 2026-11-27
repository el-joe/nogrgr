<?php

namespace App\Modules\Storefront\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ADM-089a approve / reject: a note (required when rejecting — the store sees it).
 */
class CustomThemeReviewRequest extends FormRequest
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
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'required_if:decision,reject', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['note' => __('content::custom_themes.review.note')];
    }

    public function approves(): bool
    {
        return $this->validated('decision') === 'approve';
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) ? $note : null;
    }
}
