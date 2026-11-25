<?php

namespace App\Modules\Identity\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Auth guards (config/auth.php). Central: admin, affiliate. Tenant: vendor, customer, sanctum.
 */
enum Guard: string
{
    use HasEnumHelpers;

    case Admin = 'admin';
    case Affiliate = 'affiliate';
    case Vendor = 'vendor';
    case Customer = 'customer';
    case Sanctum = 'sanctum';

    public function isCentral(): bool
    {
        return in_array($this, [self::Admin, self::Affiliate], true);
    }

    /**
     * Password broker (config/auth.php `passwords`) of the guard's user provider.
     */
    public function passwordBroker(): string
    {
        return match ($this) {
            self::Admin => 'admins',
            self::Affiliate => 'affiliates',
            self::Vendor => 'vendor_staff',
            self::Customer, self::Sanctum => 'customers',
        };
    }
}
