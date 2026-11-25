<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Platform\Services\VendorRegistrationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Add a custom domain to a store: a valid host name, not the platform base domain (or
 * one of its sub-domains), and not already pending / connected for any store.
 */
class DomainStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('host'))) {
            $host = Str::lower(trim($this->input('host')));
            $host = (string) preg_replace('#^https?://#', '', $host);
            $this->merge(['host' => rtrim($host, './')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $registrations = app(VendorRegistrationService::class);

        return [
            'host' => ['bail', 'required', 'string', 'max:253', 'regex:'.VendorCreateRequest::HOST_PATTERN, static function (string $attribute, mixed $value, Closure $fail) use ($registrations): void {
                $base = Str::lower((string) config('tenancy.base_domain'));

                if (is_string($value) && $base !== '' && ($value === $base || Str::endsWith($value, '.'.$base))) {
                    $fail(__('platform::domains.validation.platform_domain', ['base' => $base]));

                    return;
                }

                if (is_string($value) && ! $registrations->domainAvailable($value)) {
                    $fail(__('platform::messages.domain_taken', ['host' => $value]));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['host.regex' => __('platform::domains.validation.host_format')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['host' => __('platform::domains.fields.host')];
    }

    public function host(): string
    {
        return (string) $this->validated('host');
    }
}
