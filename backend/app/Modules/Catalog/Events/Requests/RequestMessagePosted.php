<?php

namespace App\Modules\Catalog\Events\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Models\Requests\RequestMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A message was posted to a request conversation (admin or vendor side).
 */
final class RequestMessagePosted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model&ThreadedRequest $request,
        public readonly RequestMessage $message,
    ) {}
}
