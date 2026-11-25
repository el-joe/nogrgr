<?php

namespace App\Modules\Platform\Rules;

use App\Modules\Platform\Support\TenantSlug;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Use in registration / tenant FormRequests together with `unique:tenants,slug`.
 */
class ValidTenantSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! TenantSlug::isValid($value)) {
            $fail(__('platform::validation.tenant_slug'));
        }
    }
}
