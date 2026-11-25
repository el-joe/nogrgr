<?php

namespace App\Modules\Billing\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Gateways that settle platform charges (registrations, renewals, add-ons).
 */
enum PaymentGateway: string
{
    use HasEnumHelpers;

    case Stripe = 'stripe';
    case Moyasar = 'moyasar';
    case Paymob = 'paymob';
    case Tap = 'tap';
    case Paypal = 'paypal';
    case Manual = 'manual';
}
