<?php

namespace App\Modules\Notifications\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Central templates were published to tenants ("Sync to tenants"). Tenant-side
 * listeners copy them into each store's own templates (keeping vendor overrides).
 */
final class EmailTemplatesSynced
{
    use Dispatchable;

    /**
     * @param  list<array{event: string, audience: string, status: string, subject: array<string, string>, body: array<string, string>}>  $templates
     */
    public function __construct(public readonly array $templates) {}
}
