<?php

namespace App\Modules\Api\Http\Middleware;

use App\Shared\Http\JsonResponder;
use App\Shared\Routing\StoreApiSignedUrl;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

/**
 * Store API tenant identification: the mobile app sends `X-Tenant`; storefront themes
 * (and gateway webhooks) call the store's own domain / sub-domain. A valid relative
 * signed link (StoreApiSignedUrl: file links opened by Image.network / the in-app browser)
 * names its store in the signed `tenant` parameter, which wins over header and host
 * (RQA-APP-016); `signed:relative` + EnsureSignedStoreLink re-check it on the route.
 */
class InitializeStoreApiTenancy
{
    public function __construct(
        private readonly InitializeTenancyByRequestData $byHeader,
        private readonly PreventAccessFromCentralDomains $preventCentral,
        private readonly InitializeTenancyByDomainOrSubdomain $byDomain,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        try {
            $signed = self::signedTenant($request);

            if ($signed !== null) {
                $request->headers->set(self::header(), $signed);
            }

            if (self::usesHeader($request)) {
                return $this->byHeader->handle($request, $next);
            }

            return $this->preventCentral->handle($request, fn (Request $request): mixed => $this->byDomain->handle($request, $next));
        } catch (TenantCouldNotBeIdentifiedException) {
            return JsonResponder::error((string) __('api::store.tenant_not_found'), 404);
        }
    }

    public static function usesHeader(Request $request): bool
    {
        return filled($request->header(self::header()));
    }

    /**
     * Store key of a valid, unexpired relative signed link that carries `tenant`.
     */
    private static function signedTenant(Request $request): ?string
    {
        $tenant = $request->query(StoreApiSignedUrl::parameter());

        if (! is_string($tenant) || $tenant === '' || ! $request->has('signature')) {
            return null;
        }

        return $request->hasValidRelativeSignature() ? $tenant : null;
    }

    private static function header(): string
    {
        return InitializeTenancyByRequestData::$header ?? 'X-Tenant';
    }
}
