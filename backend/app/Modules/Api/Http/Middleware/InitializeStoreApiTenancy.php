<?php

namespace App\Modules\Api\Http\Middleware;

use App\Shared\Http\JsonResponder;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

/**
 * Store API tenant identification: the mobile app sends `X-Tenant`; storefront themes
 * (and gateway webhooks) call the store's own domain / sub-domain.
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
        return filled($request->header(InitializeTenancyByRequestData::$header ?? 'X-Tenant'));
    }
}
