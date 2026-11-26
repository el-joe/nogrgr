<?php

namespace App\Modules\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Was this helpful?" vote on a help article.
 */
class HelpFeedbackRequest extends FormRequest
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
        return ['helpful' => ['required', 'boolean']];
    }

    public function helpful(): bool
    {
        return $this->boolean('helpful');
    }
}
