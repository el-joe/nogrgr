<?php

namespace App\Modules\Api\Enums;

use App\Shared\Enums\Concerns\HasEnumHelpers;

/**
 * Switchable app features (bootstrap `features`), stored per store in the mobile app
 * settings; a missing entry uses defaultEnabled().
 */
enum MobileFeature: string
{
    use HasEnumHelpers;

    case Reviews = 'reviews';
    case Wishlist = 'wishlist';
    case Returns = 'returns';
    case ImageSearch = 'image_search';
    case GuestCheckout = 'guest_checkout';
    case CurrencySwitcher = 'currency_switcher';
    case PushNotifications = 'push_notifications';

    public function defaultEnabled(): bool
    {
        return $this !== self::GuestCheckout;
    }
}
