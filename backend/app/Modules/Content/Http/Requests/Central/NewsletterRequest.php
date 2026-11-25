<?php

namespace App\Modules\Content\Http\Requests\Central;

use App\Modules\Content\Http\Requests\Concerns\DetectsSpam;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Footer newsletter signup (WEB-015). Throttled per IP by the route.
 */
class NewsletterRequest extends FormRequest
{
    use DetectsSpam;

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
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'website' => ['nullable', 'string', 'max:255'],
            'form_started_at' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => __('content::central.newsletter.email')];
    }
}
