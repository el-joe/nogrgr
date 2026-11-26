<?php

namespace App\Modules\Storefront\Listeners;

use App\Modules\Platform\Events\TenantProvisioned;
use App\Modules\Storefront\Services\Pages\StorePageService;
use Throwable;

/**
 * A new store gets its system CMS pages (about, contact, terms, …) right after
 * provisioning. Failures are reported, never block the store (the sync command repairs).
 */
class EnsureStorePages
{
    public function __construct(private readonly StorePageService $pages) {}

    public function handle(TenantProvisioned $event): void
    {
        try {
            $event->tenant->run(fn (): int => $this->pages->ensureSystemPages((string) $event->tenant->name));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
