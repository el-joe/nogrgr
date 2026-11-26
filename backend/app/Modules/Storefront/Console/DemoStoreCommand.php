<?php

namespace App\Modules\Storefront\Console;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Database\Seeders\DemoStoreSeeder;
use Illuminate\Console\Command;

/**
 * Fills a QA / demo store with the Ecommet demo catalog (never run on production data).
 */
class DemoStoreCommand extends Command
{
    protected $signature = 'storefront:demo-store {store : Store slug or id}';

    protected $description = 'Fill a QA store with demo products, banners, promotions, pages and reviews';

    public function handle(): int
    {
        $key = (string) $this->argument('store');
        $tenant = Tenant::query()->where('slug', $key)->when(ctype_digit($key), fn ($query) => $query->orWhere('id', (int) $key))->first();

        if (! $tenant instanceof Tenant) {
            $this->components->error("Store {$key} not found.");

            return self::FAILURE;
        }

        (new DemoStoreSeeder)->setContainer($this->laravel)->setCommand($this)->forTenant($tenant)->run();

        return self::SUCCESS;
    }
}
