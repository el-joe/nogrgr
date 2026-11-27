<?php

namespace App\Modules\Notifications\Http\Requests\Vendor;

use App\Modules\Integrations\Enums\MailEncryption;
use App\Modules\Notifications\DTOs\StoreMailSettingsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Store SMTP (VEN-081): host, port, encryption, username, password (empty = keep the
 * saved one), from address / name. Used by save and "Send test email" (+ test_email).
 * RQA-VEN-012: a save with host, username, password and from address all empty means
 * "use Nogrgr mail" (the controller clears the store server); the server fields are only
 * required when the store uses its own SMTP.
 */
class StoreMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $trim = fn (string $key): mixed => is_string($this->input($key)) ? trim((string) $this->input($key)) : $this->input($key);

        $this->merge([
            'host' => $trim('host'),
            'username' => $trim('username') ?: null,
            'from_address' => $trim('from_address'),
            'from_name' => $trim('from_name') ?: null,
            'password' => is_string($this->input('password')) && $this->input('password') !== '' ? $this->input('password') : null,
        ]);
    }

    /**
     * Own SMTP server = any server field typed (or a test send). All empty = platform mail.
     */
    public function usesOwnServer(): bool
    {
        if ($this->routeIs('*.settings.mail.test')) {
            return true;
        }

        foreach (['host', 'username', 'password', 'from_address'] as $key) {
            if ($this->filled($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->usesOwnServer()) {
            return [
                'host' => ['nullable'],
                'port' => ['nullable', 'integer', 'between:1,65535'],
                'encryption' => ['nullable', Rule::enum(MailEncryption::class)],
                'from_name' => ['nullable', 'string', 'max:120'],
            ];
        }

        $rules = [
            'host' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::enum(MailEncryption::class)],
            'username' => ['nullable', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'max:191'],
            'from_address' => ['required', 'email:rfc', 'max:191'],
            'from_name' => ['nullable', 'string', 'max:120'],
        ];

        if ($this->routeIs('*.settings.mail.test')) {
            $rules['test_email'] = ['required', 'email:rfc', 'max:191'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'host' => __('notifications::vendor_mail.fields.host'),
            'port' => __('notifications::vendor_mail.fields.port'),
            'encryption' => __('notifications::vendor_mail.fields.encryption'),
            'username' => __('notifications::vendor_mail.fields.username'),
            'password' => __('notifications::vendor_mail.fields.password'),
            'from_address' => __('notifications::vendor_mail.fields.from_address'),
            'from_name' => __('notifications::vendor_mail.fields.from_name'),
            'test_email' => __('notifications::vendor_mail.fields.test_email'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['host.regex' => __('notifications::vendor_mail.validation.host')];
    }

    public function toData(): StoreMailSettingsData
    {
        return new StoreMailSettingsData(
            host: strtolower((string) $this->validated('host')),
            port: (int) $this->validated('port'),
            encryption: MailEncryption::from((string) $this->validated('encryption')),
            username: $this->validated('username'),
            password: $this->validated('password'),
            fromAddress: (string) $this->validated('from_address'),
            fromName: $this->validated('from_name'),
        );
    }
}
