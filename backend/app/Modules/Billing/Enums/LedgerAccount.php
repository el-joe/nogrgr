<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Ledger accounts (decision #72). vendor_payable = the platform owes the store; vendor_receivable = the store owes the platform; platform_cash / platform_revenue = the platform's counter accounts.
 */
enum LedgerAccount: string
{
    use HasEnumHelpers;

    case VendorPayable = 'vendor_payable';
    case VendorReceivable = 'vendor_receivable';
    case PlatformCash = 'platform_cash';
    case PlatformRevenue = 'platform_revenue';
}
