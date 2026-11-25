<?php

namespace App\Modules\Api;

use App\Modules\Api\Support\StoreApiCustomer;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [];

    public function boot(): void
    {
        parent::boot();

        // Store API: per store and per customer (else per client IP).
        RateLimiter::for('store-api', static fn (Request $request): Limit => Limit::perMinute((int) config('commerce.store_api_rate_limit', 120))
            ->by(self::throttleKey($request)));

        RateLimiter::for('store-api-checkout', static fn (Request $request): Limit => Limit::perMinute((int) config('commerce.checkout_rate_limit', 10))
            ->by('checkout|'.self::throttleKey($request)));
    }

    private static function throttleKey(Request $request): string
    {
        $tenant = tenancy()->initialized ? (string) tenant()?->getTenantKey() : 'central';
        $customer = StoreApiCustomer::resolve($request);

        return $tenant.'|'.($customer !== null ? 'c'.$customer->id : $request->ip());
    }
}
