<?php

namespace App\Modules\Catalog\Events\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A store cancelled its own request (→ admin notification).
 */
final class RequestCancelledByVendor
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Model&ThreadedRequest $request) {}
}
