<?php

namespace App\Shared\Routing;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

/**
 * Temporary signed Store API file link that identifies its store by itself (RQA-APP-016,
 * decision #128): the current store's key is added as the signed `tenant` query parameter,
 * so the app can load it in Image.network / the in-app browser without `X-Tenant`, on the
 * platform host or the store's own domain. The signature is relative (any host) and covers
 * the tenant, so a link signed for store A never opens a file of store B.
 */
final class StoreApiSignedUrl
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function temporary(string $route, DateTimeInterface $expires, array $parameters): string
    {
        $tenant = tenancy()->initialized ? tenant()?->getTenantKey() : null;
        $parameters += array_filter([self::parameter() => $tenant !== null ? (string) $tenant : null]);

        return url(URL::temporarySignedRoute($route, $expires, $parameters, absolute: false));
    }

    public static function parameter(): string
    {
        return InitializeTenancyByRequestData::$queryParameter ?? 'tenant';
    }
}
