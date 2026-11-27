<?php

namespace App\Modules\Api\Http\Middleware;

use App\Shared\Http\JsonResponder;
use App\Shared\Routing\StoreApiSignedUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Store API signed file routes (after `signed:relative`): the link must name, inside its
 * signature, the store the request runs in (RQA-APP-016). Links without the signed
 * `tenant` parameter, or of another store, are refused.
 */
class EnsureSignedStoreLink
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $signed = $request->query(StoreApiSignedUrl::parameter());
        $current = tenancy()->initialized ? tenant()?->getTenantKey() : null;

        if (! is_string($signed) || $current === null || $signed !== (string) $current) {
            return JsonResponder::error((string) __('api::store.invalid_link'), 403);
        }

        return $next($request);
    }
}
