<?php

namespace App\Modules\Engagement\Http\Requests\Storefront;

use App\Modules\Engagement\DTOs\SupportRequestData;
use App\Modules\Engagement\Enums\SupportSubject;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Storefront support form (SF-ECO-035): name, email, phone (intl-tel-input → E.164),
 * order number, subject, message (≤ 1000), up to 3 photos (JPG / PNG ≤ 5 MB); `website` is a honeypot that must stay empty.
 */
class StoreSupportRequest extends FormRequest
{
    use NormalizesPhoneInput;

    public const MESSAGE_MAX = 1000;

    public const MAX_FILES = 3;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'email', 'order_number', 'message'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim(strip_tags((string) $this->input($field)))]);
            }
        }

        if (blank($this->input('phone'))) {
            $this->merge(['phone' => null, 'phone_country' => null]);
        }

        $this->normalizePhoneInput();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:filter', 'max:160'],
            'phone_country' => ['required_with:phone', 'nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('phone_country')],
            'order_number' => ['nullable', 'string', 'max:32'],
            'subject' => ['required', Rule::enum(SupportSubject::class)],
            'message' => ['required', 'string', 'min:10', 'max:'.self::MESSAGE_MAX],
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'attachments.*' => ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __st('support.fields.name'),
            'email' => __st('support.fields.email'),
            'phone' => __st('support.fields.phone'),
            'phone_country' => __st('support.fields.phone'),
            'order_number' => __st('support.fields.order_number'),
            'subject' => __st('support.fields.subject'),
            'attachments' => __st('support.fields.attachments'),
            'attachments.*' => __st('support.fields.attachments'),
            'message' => __st('support.fields.message'),
        ];
    }

    public function toData(?int $customerId): SupportRequestData
    {
        $phone = $this->validated('phone');

        return new SupportRequestData(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
            subject: SupportSubject::from((string) $this->validated('subject')),
            message: (string) $this->validated('message'),
            phone: is_string($phone) && $phone !== '' ? $phone : null,
            orderNumber: filled($this->validated('order_number')) ? ltrim((string) $this->validated('order_number'), '#') : null,
            attachments: array_values(array_filter((array) $this->file('attachments', []), static fn ($file): bool => $file instanceof UploadedFile)),
            customerId: $customerId,
            locale: app()->getLocale(),
            ipAddress: $this->ip(),
        );
    }
}
