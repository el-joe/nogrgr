<?php

namespace App\Modules\Storefront\Console;

use App\Modules\Localization\Models\Country;
use App\Modules\Platform\DTOs\ProvisionTenantData;
use App\Modules\Platform\Enums\TenantStatus;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Services\TenantProvisioningService;
use App\Modules\Storefront\Database\Seeders\DemoStoreSeeder;
use App\Shared\Tenancy\CentralDomains;
use Illuminate\Console\Command;

/**
 * Fills a QA / demo store with the Ecommet demo catalog (never run on production data).
 * `--platform` makes the store the platform demo store behind the central theme previews
 * (WEB-004, decision #117 a): the store is provisioned when it does not exist yet, flagged
 * `is_demo` (read-only, noindex) and sells every catalogue category, so every demo banner
 * link resolves (QA-FINAL-007).
 */
class DemoStoreCommand extends Command
{
    protected $signature = 'storefront:demo-store {store : Store slug or id}
        {--platform : Create the store if missing and make it the platform demo store for the theme previews}';

    /** Shopping countries of a newly provisioned platform demo store. */
    private const DEMO_COUNTRIES = ['SA', 'AE', 'EG', 'KW', 'QA', 'BH', 'OM', 'JO', 'US', 'GB'];

    protected $description = 'Fill a QA store with demo products, banners, promotions, pages and reviews';

    public function handle(): int
    {
        $key = (string) $this->argument('store');
        $tenant = Tenant::query()->where('slug', $key)->when(ctype_digit($key), fn ($query) => $query->orWhere('id', (int) $key))->first();

        if (! $tenant instanceof Tenant && $this->option('platform') && ! ctype_digit($key)) {
            $tenant = $this->provisionPlatformStore($key);
        }

        if (! $tenant instanceof Tenant) {
            $this->components->error("Store {$key} not found.");

            return self::FAILURE;
        }

        if ($this->option('platform')) {
            $tenant->forceFill(['selling_category_ids' => null])->save();
            app(TenantRepositoryContract::class)->markPlatformDemoStore($tenant);
        }

        (new DemoStoreSeeder)->setContainer($this->laravel)->setCommand($this)->forTenant($tenant)->run();

        if ($this->option('platform')) {
            $this->components->info("{$tenant->slug} is the platform demo store for the theme previews.");
        }

        return self::SUCCESS;
    }

    private function provisionPlatformStore(string $slug): ?Tenant
    {
        $countries = Country::query()->where('is_active', true);
        $countryIds = (clone $countries)->whereIn('iso2', self::DEMO_COUNTRIES)->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all()
            ?: array_filter([(int) $countries->orderBy('id')->value('id')]);
        $provisioning = app(TenantProvisioningService::class);

        $tenant = $provisioning->provision(new ProvisionTenantData(
            storeName: 'Nogrgr Demo',
            slug: $slug,
            ownerName: 'Nogrgr Demo',
            ownerEmail: $slug.'@'.CentralDomains::primary(),
            defaultCountryId: array_values($countryIds)[0] ?? null,
            sellingCountryIds: array_values($countryIds),
            locale: 'en',
        ));

        $tenant = $provisioning->run($tenant);

        return $tenant->status === TenantStatus::Active ? $tenant : null;
    }
}
