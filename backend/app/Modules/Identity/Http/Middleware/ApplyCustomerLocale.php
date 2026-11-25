<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Models\Customer;
use App\Modules\Identity\Services\CustomerProfileService;
use App\Shared\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront language for signed-in customers: `?lang=` (already applied by SetLocale)
 * is saved on the account; otherwise the account's saved language is applied.
 * Guests keep the cookie / default language from SetLocale.
 */
class ApplyCustomerLocale
{
    public function __construct(private readonly CustomerProfileService $profiles) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer instanceof Customer) {
            $requested = $request->query('lang');

            if (is_string($requested) && Locale::isSupported($requested)) {
                $this->profiles->rememberLocale($customer, $requested);
            } elseif (Locale::isSupported($customer->locale)) {
                app()->setLocale((string) $customer->locale);
            }
        }

        return $next($request);
    }
}
