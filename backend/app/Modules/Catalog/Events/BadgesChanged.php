<?php

namespace App\Modules\Catalog\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A badge list or its settings changed (fired after commit): tenantId = the store whose own
 * badge placement changed, null = the platform's badge (every store). The storefront
 * rebuilds its product index so cards show the change at once.
 */
final class BadgesChanged
{
    use Dispatchable;

    public function __construct(public readonly ?int $tenantId = null) {}
}
