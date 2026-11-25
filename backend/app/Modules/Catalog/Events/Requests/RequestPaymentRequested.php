<?php

namespace App\Modules\Catalog\Events\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Models\Requests\RequestPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment request (with its invoice) was created or cancelled ($cancelled) on a request.
 */
final class RequestPaymentRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model&ThreadedRequest $request,
        public readonly RequestPayment $payment,
        public readonly bool $cancelled = false,
    ) {}
}
