<?php

namespace App\Modules\Catalog\Events\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An admin moved a product / manufacturing / brand request to another status
 * (or archived / restored a product request: $to = "archived" / "restored").
 */
final class RequestStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model&ThreadedRequest $request,
        public readonly string $from,
        public readonly string $to,
    ) {}
}
