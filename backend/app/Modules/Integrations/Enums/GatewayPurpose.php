<?php

namespace App\Modules\Integrations\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * The two independent configs of a central gateway: vendors paying the platform
 * (dues, manufacturing / brand requests, payment requests) and plan checkout during
 * sign-up and renewals.
 */
enum GatewayPurpose: string
{
    use HasEnumHelpers;

    case VendorPayments = 'vendor_payments';
    case TenantRegistration = 'tenant_registration';

    public function icon(): string
    {
        return match ($this) {
            self::VendorPayments => 'store',
            self::TenantRegistration => 'credit-card',
        };
    }
}
