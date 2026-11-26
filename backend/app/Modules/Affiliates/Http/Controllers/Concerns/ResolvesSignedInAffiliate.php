<?php

namespace App\Modules\Affiliates\Http\Controllers\Concerns;

use App\Modules\Identity\Models\Affiliate;
use Illuminate\Http\Request;

/**
 * The signed-in affiliate with every column loaded (a guard user built in memory — e.g.
 * right after sign-up or SSO — lacks the database defaults such as `balance`).
 */
trait ResolvesSignedInAffiliate
{
    private function affiliate(Request $request): Affiliate
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        if (! array_key_exists('balance', $affiliate->getAttributes()) || ! array_key_exists('phone', $affiliate->getAttributes())) {
            $affiliate->refresh();
        }

        return $affiliate;
    }
}
