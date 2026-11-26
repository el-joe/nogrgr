<?php

namespace App\Modules\Engagement\Events;

use App\Modules\Engagement\Models\SupportRequest;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A visitor sent the storefront support form; the store owners are notified.
 */
final class SupportRequestReceived
{
    use Dispatchable;

    public function __construct(public readonly SupportRequest $request, public readonly int $tenantId) {}
}
