<?php

namespace App\Modules\AfterSales\Events;

use App\Modules\AfterSales\Models\ReturnRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The customer wrote on a return thread (store owners and admins with returns.view are
 * told).
 */
final class ReturnCustomerMessaged
{
    use Dispatchable;

    public function __construct(
        public readonly ReturnRequest $return,
        public readonly string $message,
    ) {}
}
