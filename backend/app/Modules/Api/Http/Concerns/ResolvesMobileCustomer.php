<?php

namespace App\Modules\Api\Http\Concerns;

use App\Modules\Identity\Models\Customer;
use Illuminate\Http\Request;

/**
 * The signed-in customer of a Mobile API request (AuthenticateMobileCustomer put it on
 * the `customer` guard); null for guests on optional routes.
 */
trait ResolvesMobileCustomer
{
    protected function customer(Request $request): Customer
    {
        $customer = $request->user('customer');
        abort_unless($customer instanceof Customer, 401);

        return $customer;
    }

    protected function optionalCustomer(Request $request): ?Customer
    {
        $customer = $request->user('customer');

        return $customer instanceof Customer ? $customer : null;
    }
}
