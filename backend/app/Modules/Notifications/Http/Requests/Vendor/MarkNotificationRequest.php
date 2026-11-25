<?php

namespace App\Modules\Notifications\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH {read: 1|0} — mark one inbox row read or unread.
 */
class MarkNotificationRequest extends FormRequest
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
            'read' => ['required', 'boolean'],
        ];
    }

    public function read(): bool
    {
        return $this->boolean('read');
    }
}
