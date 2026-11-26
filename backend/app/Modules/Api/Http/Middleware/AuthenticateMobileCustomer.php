<?php

namespace App\Modules\Api\Http\Middleware;

use App\Modules\Api\Enums\MobileTokenAbility;
use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Customer;
use App\Shared\Http\JsonResponder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile API customer routes: a Sanctum **access** token of an active customer of the
 * current store (tokens live in the store DB, so another store's token never matches).
 * The customer is also set on the `customer` guard for the request, so the storefront
 * account FormRequests and services (`user('customer')`, `current_password:customer`)
 * work unchanged. `optional` lets guests through (no 401).
 */
class AuthenticateMobileCustomer
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'required'): mixed
    {
        $customer = Auth::guard('sanctum')->user();
        $valid = $customer instanceof Customer
            && $customer->tokenCan(MobileTokenAbility::Access->value)
            && $customer->status === AccountStatus::Active;

        if ($valid) {
            Auth::guard('customer')->setUser($customer);
            Auth::shouldUse('sanctum');
        } elseif ($mode !== 'optional') {
            return JsonResponder::error((string) __('api::mobile.unauthenticated'), 401);
        }

        return $next($request);
    }
}
