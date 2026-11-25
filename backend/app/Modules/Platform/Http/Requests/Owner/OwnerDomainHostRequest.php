<?php

namespace App\Modules\Platform\Http\Requests\Owner;

use App\Modules\Platform\Http\Requests\Admin\VendorCreateRequest;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Host field of the owner domain modals (OWN-004a / 004b): "https://" and trailing
 * slashes are dropped, then the host must match the domain pattern, must not be on
 * the platform base domain and must not be pending / connected for any store.
 */
abstract class OwnerDomainHostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The request being edited (excluded from the "already used" check).
     */
    protected function exceptId(): ?int
    {
        return null;
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
     * @return list<mixed>
     */
    protected function hostRules(): array
    {
        $requests = app(DomainRequestRepositoryContract::class);
        $exceptId = $this->exceptId();

        return ['bail', 'required', 'string', 'max:253', 'regex:'.VendorCreateRequest::HOST_PATTERN, static function (string $attribute, mixed $value, Closure $fail) use ($requests, $exceptId): void {
            $base = Str::lower((string) config('tenancy.base_domain'));

            if (is_string($value) && $base !== '' && ($value === $base || Str::endsWith($value, '.'.$base))) {
                $fail(__('platform::domains.validation.platform_domain', ['base' => $base]));

                return;
            }

            if (is_string($value) && $requests->hostInUse($value, $exceptId)) {
                $fail(__('platform::messages.domain_taken', ['host' => $value]));
            }
        }];
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
        return ['host' => __('platform::domains.fields.host'), 'tenant_id' => __('platform::owner.domains.store')];
    }

    public function host(): string
    {
        return (string) $this->validated('host');
    }
}
