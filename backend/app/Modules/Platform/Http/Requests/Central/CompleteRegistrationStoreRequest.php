<?php

namespace App\Modules\Platform\Http\Requests\Central;

use App\Modules\Platform\Rules\ValidTenantSlug;
use App\Modules\Platform\Services\VendorRegistrationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Complete registration › store step (WEB-018a): store name, sub-domain (always: the
 * store also lives there while a custom domain is connected), optional custom domain
 * and the default profit margin (percent with up to two decimals → basis points).
 */
class CompleteRegistrationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'store_name' => is_string($this->input('store_name')) ? trim($this->input('store_name')) : $this->input('store_name'),
            'slug' => StoreAddressCheck::normalizeSlug($this->input('slug')),
            'custom_domain' => $this->input('domain_type') === 'custom' ? StoreAddressCheck::normalizeDomain($this->input('custom_domain')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $check = new StoreAddressCheck(app(VendorRegistrationService::class));
        $registrationId = app(VendorRegistrationService::class)->resolveToken((string) $this->input('token'))?->id;

        return [
            'token' => ['required', 'string', 'max:128'],
            'store_name' => ['required', 'string', 'max:120'],
            'slug' => ['bail', 'required', 'string', 'max:63', new ValidTenantSlug, function (string $attribute, mixed $value, Closure $fail) use ($check, $registrationId): void {
                $error = $check->slugError((string) $value, $registrationId);

                if ($error !== null) {
                    $fail($error);
                }
            }],
            'domain_type' => ['required', Rule::in(['subdomain', 'custom'])],
            'custom_domain' => ['bail', 'nullable', 'required_if:domain_type,custom', 'string', 'max:253', function (string $attribute, mixed $value, Closure $fail) use ($check): void {
                $error = $check->domainError((string) $value);

                if ($error !== null) {
                    $fail($error);
                }
            }],
            'profit_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }

    public function profitPercentBps(): int
    {
        return (int) round(((float) $this->validated('profit_percent')) * 100);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'store_name' => __('platform::register.store.store_name'),
            'slug' => __('platform::register.store.address'),
            'domain_type' => __('platform::register.store.domain_type'),
            'custom_domain' => __('platform::register.store.custom_domain'),
            'profit_percent' => __('platform::register.store.profit'),
        ];
    }
}
