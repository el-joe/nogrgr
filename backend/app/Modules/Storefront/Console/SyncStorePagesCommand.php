<?php

namespace App\Modules\Storefront\Console;

use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Services\Pages\StorePageService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Creates the missing system CMS pages (about, contact, terms, …) in every active store
 * (or the given ids). New stores get them on provisioning (EnsureStorePages listener).
 */
class SyncStorePagesCommand extends Command
{
    protected $signature = 'storefront:sync-store-pages {--tenant=* : Only these store ids}';

    protected $description = 'Create the missing system CMS pages in every store';

    public function handle(StorePageService $pages): int
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->option('tenant'))));
        $created = 0;
        $stores = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active->value)
            ->when($ids !== [], fn ($query) => $query->whereKey($ids))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($pages, &$created, &$stores): void {
                try {
                    $created += (int) $tenant->run(fn (): int => $pages->ensureSystemPages((string) $tenant->name));
                    $stores++;
                } catch (Throwable $exception) {
                    report($exception);
                    $this->components->warn("Store {$tenant->id}: {$exception->getMessage()}");
                }
            });

        $this->components->info("Created {$created} page(s) in {$stores} store(s).");

        return self::SUCCESS;
    }
}
