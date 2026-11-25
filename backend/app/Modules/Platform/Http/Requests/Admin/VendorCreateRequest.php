<?php

namespace App\Modules\Platform\Http\Requests\Admin;

use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Repositories\Contracts\PlanRepositoryContract;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhoneInput;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Enums\PlanAssignment;
use App\Modules\Platform\Rules\ValidTenantSlug;
use App\Modules\Platform\Services\VendorRegistrationService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Admin › Vendors › Create vendor (ADM-040a): owner, store, plan. Percentages are
 * converted to basis points in payload().
 */
class VendorCreateRequest extends FormRequest
{
    use NormalizesPhoneInput;

    /** Host name: dot-separated DNS labels with an alphabetic TLD. */
    public const HOST_PATTERN = '/^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/';

    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->can('vendors.manage');
    }

    protected function prepareForValidation(): void
    {
        foreach (['owner_email', 'slug', 'custom_domain'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => Str::lower(trim($this->input($field)))]);
            }
        }

        if ($this->input('custom_domain') === '') {
            $this->merge(['custom_domain' => null]);
        }

        if ($this->input('plan_id') === '' || $this->input('plan_id') === null) {
            $this->merge(['plan_id' => null, 'term' => null, 'plan_assignment' => null]);
        }

        $this->normalizePhoneInput('owner_phone', 'owner_phone_country');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $registrations = app(VendorRegistrationService::class);
        $plans = array_keys(app(PlanRepositoryContract::class)->options(activeOnly: true));

        return [
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'string', 'email', 'max:191'],
            'owner_phone_country' => ['required_with:owner_phone', 'nullable', 'string', 'size:2', $this->phoneRegionRule()],
            'owner_phone' => ['nullable', 'string', 'max:32', (new Phone)->countryField('owner_phone_country')],
            'owner_password' => ['required', 'string', 'max:72', Password::min(8)->letters()->numbers()],
            'store_name' => ['required', 'string', 'max:120'],
            'slug' => ['bail', 'required', 'string', 'max:63', new ValidTenantSlug, static function (string $attribute, mixed $value, Closure $fail) use ($registrations): void {
                if (is_string($value) && ! $registrations->slugAvailable($value)) {
                    $fail(__('platform::vendors.create.slug_taken'));
                }
            }],
            'custom_domain' => ['bail', 'nullable', 'string', 'max:253', 'regex:'.self::HOST_PATTERN, static function (string $attribute, mixed $value, Closure $fail) use ($registrations): void {
                $base = Str::lower((string) config('tenancy.base_domain'));

                if (is_string($value) && $base !== '' && ($value === $base || Str::endsWith($value, '.'.$base))) {
                    $fail(__('platform::vendors.create.domain_is_platform'));

                    return;
                }

                if (is_string($value) && ! $registrations->domainAvailable($value)) {
                    $fail(__('platform::messages.domain_taken', ['host' => $value]));
                }
            }],
            'default_country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'selling_country_ids' => ['nullable', 'array', 'max:250'],
            'selling_country_ids.*' => ['integer', 'distinct', Rule::exists(Country::class, 'id')],
            'profit_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'plan_id' => ['nullable', 'integer', Rule::in($plans)],
            'term' => ['nullable', 'required_with:plan_id', Rule::enum(BillingTerm::class)],
            'plan_assignment' => ['nullable', 'required_with:plan_id', Rule::enum(PlanAssignment::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'owner_name' => __('platform::vendors.fields.owner_name'),
            'owner_email' => __('platform::vendors.fields.owner_email'),
            'owner_phone' => __('platform::vendors.fields.owner_phone'),
            'owner_phone_country' => __('platform::vendors.fields.owner_phone'),
            'owner_password' => __('platform::vendors.fields.owner_password'),
            'store_name' => __('platform::vendors.fields.store_name'),
            'slug' => __('platform::vendors.fields.slug'),
            'custom_domain' => __('platform::vendors.fields.custom_domain'),
            'default_country_id' => __('platform::vendors.fields.default_country'),
            'selling_country_ids' => __('platform::vendors.fields.countries'),
            'selling_country_ids.*' => __('platform::vendors.fields.countries'),
            'profit_percent' => __('platform::vendors.fields.profit_percent'),
            'plan_id' => __('platform::vendors.fields.plan'),
            'term' => __('platform::vendors.fields.term'),
            'plan_assignment' => __('platform::vendors.fields.plan_assignment'),
        ];
    }

    /**
     * Input for ProvisionTenantData.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();
        $planId = $data['plan_id'] ?? null;

        return [
            'store_name' => trim((string) $data['store_name']),
            'slug' => (string) $data['slug'],
            'owner_name' => trim((string) $data['owner_name']),
            'owner_email' => (string) $data['owner_email'],
            'owner_phone' => $data['owner_phone'] ?? null,
            'owner_password' => (string) $data['owner_password'],
            'custom_domain' => $data['custom_domain'] ?? null,
            'default_country_id' => isset($data['default_country_id']) ? (int) $data['default_country_id'] : null,
            'selling_country_ids' => array_values(array_map('intval', (array) ($data['selling_country_ids'] ?? []))),
            'profit_percent_bps' => self::toBps($data['profit_percent']),
            'plan_id' => $planId !== null ? (int) $planId : null,
            'term' => $planId !== null ? BillingTerm::from((string) $data['term']) : null,
            'plan_assignment' => $planId !== null ? PlanAssignment::from((string) $data['plan_assignment']) : PlanAssignment::Trial,
            'locale' => app()->getLocale(),
            'admin_id' => $this->user('admin')?->getAuthIdentifier(),
        ];
    }

    /**
     * "62.5" (%) → 6250 basis points, without float drift.
     */
    public static function toBps(mixed $percent): int
    {
        return (int) round(((float) $percent) * 100);
    }
}
