<?php

namespace App\Modules\Platform\Events;

use App\Modules\Platform\Models\DomainRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class DomainConnected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly DomainRequest $request) {}
}
