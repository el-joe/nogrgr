<?php

namespace App\Modules\Platform\Legacy\Services;

use App\Modules\Platform\Legacy\DTOs\LegacyRunOptions;
use App\Modules\Platform\Legacy\DTOs\LegacySourceConfig;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\Central\AdminsStep;
use App\Modules\Platform\Legacy\Steps\Central\AffiliatesStep;
use App\Modules\Platform\Legacy\Steps\Central\AfterSalesStep;
use App\Modules\Platform\Legacy\Steps\Central\BillingStep;
use App\Modules\Platform\Legacy\Steps\Central\CatalogStep;
use App\Modules\Platform\Legacy\Steps\Central\ContentStep;
use App\Modules\Platform\Legacy\Steps\Central\EmailTemplatesStep;
use App\Modules\Platform\Legacy\Steps\Central\GatewaysStep;
use App\Modules\Platform\Legacy\Steps\Central\LocalizationStep;
use App\Modules\Platform\Legacy\Steps\Central\PlansStep;
use App\Modules\Platform\Legacy\Steps\Central\PromotionsStep;
use App\Modules\Platform\Legacy\Steps\Central\RequestsStep;
use App\Modules\Platform\Legacy\Steps\Central\SettingsStep;
use App\Modules\Platform\Legacy\Steps\Central\SettlementsStep;
use App\Modules\Platform\Legacy\Steps\Central\ShippingStep;
use App\Modules\Platform\Legacy\Steps\Central\SupportStep;
use App\Modules\Platform\Legacy\Steps\Central\TenantsStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Steps\Tenant\CustomersStep;
use App\Modules\Platform\Legacy\Steps\Tenant\EngagementStep;
use App\Modules\Platform\Legacy\Steps\Tenant\OrdersStep;
use App\Modules\Platform\Legacy\Steps\Tenant\StoreCatalogStep;
use App\Modules\Platform\Legacy\Steps\Tenant\StorePromotionsStep;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyReport;
use App\Modules\Platform\Legacy\Support\LegacySource;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use App\Modules\Platform\Legacy\Support\LegacyWriter;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs `legacy:migrate`: central steps against the new central DB, then every store
 * step once per old store inside its tenancy, then the ledger backfill and permission
 * sync. Each step is isolated: a failure is reported and the run continues. The old
 * databases are only ever read.
 */
class LegacyMigrationService
{
    /** @var array<string, class-string<LegacyStepHandler>> */
    private const HANDLERS = [
        LegacyStep::Localization->value => LocalizationStep::class,
        LegacyStep::Plans->value => PlansStep::class,
        LegacyStep::Settings->value => SettingsStep::class,
        LegacyStep::Gateways->value => GatewaysStep::class,
        LegacyStep::Catalog->value => CatalogStep::class,
        LegacyStep::Admins->value => AdminsStep::class,
        LegacyStep::Tenants->value => TenantsStep::class,
        LegacyStep::Promotions->value => PromotionsStep::class,
        LegacyStep::Support->value => SupportStep::class,
        LegacyStep::Affiliates->value => AffiliatesStep::class,
        LegacyStep::Content->value => ContentStep::class,
        LegacyStep::EmailTemplates->value => EmailTemplatesStep::class,
        LegacyStep::Shipping->value => ShippingStep::class,
        LegacyStep::Billing->value => BillingStep::class,
        LegacyStep::Settlements->value => SettlementsStep::class,
        LegacyStep::Requests->value => RequestsStep::class,
        LegacyStep::StoreCatalog->value => StoreCatalogStep::class,
        LegacyStep::Customers->value => CustomersStep::class,
        LegacyStep::Orders->value => OrdersStep::class,
        LegacyStep::Engagement->value => EngagementStep::class,
        LegacyStep::StorePromotions->value => StorePromotionsStep::class,
        LegacyStep::AfterSales->value => AfterSalesStep::class,
    ];

    /** Old store tables counted when a store cannot be migrated yet (dry run before provisioning). */
    private const STORE_TABLES = ['products', 'tenant_product_overrides', 'customers', 'customer_addresses', 'orders', 'order_items', 'product_rates', 'favorites', 'coupons', 'flash_sales'];

    public function __construct(
        private readonly Container $container,
        private readonly LegacySource $source,
        private readonly LegacyReconciliationService $reconciliation,
    ) {}

    public function run(LegacySourceConfig $config, LegacyRunOptions $options): LegacyReport
    {
        $this->source->configure($config);
        $report = new LegacyReport;
        $connection = DB::connection((new Tenant)->getConnectionName());
        $central = new LegacyWriter($connection, 'legacy_central_ids', $options->dryRun, $report, 'central');
        $context = new LegacyContext($options, $this->source, $report, $central);

        if ($options->freshMapping) {
            $central->forget();
            $report->note('Central id mapping cleared (--fresh-mapping): rows are matched again by natural keys or re-created.');
        }

        foreach (LegacyStep::central() as $step) {
            if ($options->wants($step)) {
                $this->step($context, $step);
            }
        }

        $storeSteps = array_values(array_filter(LegacyStep::store(), $options->wants(...)));
        $migrated = [];

        if ($storeSteps !== []) {
            $migrated = $this->stores($context, $storeSteps);
        }

        foreach (LegacyStep::afterStore() as $step) {
            if ($options->wants($step)) {
                $this->step($context, $step);
            }
        }

        if (! $options->dryRun && $options->wants(LegacyStep::Finalize)) {
            $this->finalize($context, $config, $migrated);
        }

        return $report;
    }

    /**
     * @param  list<LegacyStep>  $steps
     * @return list<int> new store ids that were migrated
     */
    private function stores(LegacyContext $context, array $steps): array
    {
        $migrated = [];

        foreach ($this->source->central()->table('tenants')->orderBy('created_at')->orderBy('id')->get(['id', 'data']) as $row) {
            $legacyId = (string) $row->id;

            if (! $context->options->wantsTenant($legacyId)) {
                continue;
            }

            $data = LegacyValues::json($row->data) ?? [];
            $database = (string) ($data['tenancy_db_name'] ?? 'dokan_v2_'.$legacyId);
            $source = $this->source->store($database);
            $scope = 'store:'.$legacyId;

            if ($source === null) {
                $context->report->issue(LegacyIssueLevel::Info, $scope, 'store', $legacyId, 'Old store database not on the server: store data not migrated (not required for launch, decision #113)');

                continue;
            }

            $tenantId = $context->central->id('tenant', $legacyId);
            $tenant = $tenantId !== null && $tenantId > 0 ? Tenant::query()->find($tenantId) : null;

            if ($tenant === null) {
                foreach (self::STORE_TABLES as $table) {
                    if ($this->source->hasTable($source, $table)) {
                        $context->report->read($scope, $table, (int) $source->table($table)->count());
                    }
                }

                $context->report->note("Store {$legacyId}: not provisioned yet, old rows counted only (run the tenants step first).");

                continue;
            }

            try {
                tenancy()->initialize($tenant);

                if (! $context->dryRun() && ! DB::connection('tenant')->getSchemaBuilder()->hasTable('legacy_store_ids')) {
                    Artisan::call('tenants:migrate', ['--tenants' => [$tenant->getTenantKey()], '--force' => true]);
                }

                $writer = new LegacyWriter(DB::connection('tenant'), 'legacy_store_ids', $context->dryRun(), $context->report, $scope);

                if ($context->options->freshMapping) {
                    $writer->forget();
                }

                $context->enterStore($legacyId, $tenant, $source, $writer);

                foreach ($steps as $step) {
                    $this->step($context, $step);
                }

                $migrated[] = (int) $tenant->id;
            } finally {
                $context->leaveStore();

                if (tenancy()->initialized) {
                    tenancy()->end();
                }
            }
        }

        return $migrated;
    }

    private function step(LegacyContext $context, LegacyStep $step): void
    {
        try {
            /** @var LegacyStepHandler $handler */
            $handler = $this->container->make(self::HANDLERS[$step->value]);
            $handler->run($context);
        } catch (Throwable $exception) {
            report($exception);
            $context->report->issue(LegacyIssueLevel::Error, $context->scope(), $step->value, null, 'Step failed: '.Str::limit(class_basename($exception).': '.$exception->getMessage(), 300));
        }
    }

    /**
     * @param  list<int>  $tenantIds
     */
    private function finalize(LegacyContext $context, LegacySourceConfig $config, array $tenantIds): void
    {
        $ledger = Artisan::call('finance:backfill-ledger', $tenantIds === [] ? [] : ['--tenant' => $tenantIds]);
        $context->report->note('finance:backfill-ledger exit code '.$ledger.'.');
        $permissions = Artisan::call('permissions:sync');
        $context->report->note('permissions:sync exit code '.$permissions.'.');

        $reconciliation = $this->reconciliation->run($config);
        $context->report->section($reconciliation->markdown());

        if ($reconciliation->differences() > 0) {
            $context->report->issue(LegacyIssueLevel::Error, 'central', 'reconciliation', null, 'Finance reconciliation shows differences (see the reconciliation section)');
        }
    }
}
