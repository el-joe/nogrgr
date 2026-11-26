<?php

namespace App\Modules\Affiliates\Support;

use App\Shared\Tenancy\CentralDomains;

/**
 * Whether the current error response belongs to the affiliate panel ({central}/affiliate/…),
 * so resources/views/errors/{403,404,419,500} render the panel's own error page (AFF-015).
 */
final class AffiliateErrorPage
{
    public const CODES = ['403', '404', '419', '500'];

    public static function applies(): bool
    {
        $request = request();

        return in_array($request->getHost(), CentralDomains::all(), true)
            && ($request->is('affiliate') || $request->is('affiliate/*'));
    }
}
