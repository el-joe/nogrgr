<?php

namespace App\Modules\Api\Support;

use App\Modules\Identity\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The customer of a Store API request: a Sanctum token (mobile app) or the storefront
 * session (themes). Null = guest.
 */
final class StoreApiCustomer
{
    public static function resolve(Request $request): ?Customer
    {
        $token = Auth::guard('sanctum')->user();

        if ($token instanceof Customer) {
            return $token;
        }

        $session = Auth::guard('customer')->user();

        return $session instanceof Customer ? $session : null;
    }
}
