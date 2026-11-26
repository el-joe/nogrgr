<?php

namespace App\Modules\Ai;

use App\Modules\Ai\Console\IndexProductImagesCommand;
use App\Modules\Ai\Console\TranslateDeltasCommand;
use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\Enums\AdminTranslationKind;
use App\Modules\Ai\Enums\LogoColor;
use App\Modules\Ai\Enums\LogoStyle;
use App\Modules\Ai\Enums\TranslationPurpose;
use App\Modules\Ai\Events\AdminTranslationRunUpdated;
use App\Modules\Ai\Http\Controllers\Vendor\AiLogoController;
use App\Modules\Ai\Listeners\IndexChangedProductImages;
use App\Modules\Ai\Listeners\StartStoreTranslation;
use App\Modules\Ai\Models\AdminTranslationRun;
use App\Modules\Ai\Policies\AdminTranslationRunPolicy;
use App\Modules\Ai\Repositories\Contracts\AdminTranslationRunRepositoryContract;
use App\Modules\Ai\Repositories\Contracts\AiUsageLogRepositoryContract;
use App\Modules\Ai\Repositories\Contracts\ImageEmbeddingRepositoryContract;
use App\Modules\Ai\Repositories\Contracts\StoreTranslationItemRepositoryContract;
use App\Modules\Ai\Repositories\Eloquent\AdminTranslationRunRepository;
use App\Modules\Ai\Repositories\Eloquent\AiUsageLogRepository;
use App\Modules\Ai\Repositories\Eloquent\ImageEmbeddingRepository;
use App\Modules\Ai\Repositories\Eloquent\StoreTranslationItemRepository;
use App\Modules\Ai\Services\AdminTranslation\AdminTranslationRunService;
use App\Modules\Ai\Services\Logo\LogoGeneratorService;
use App\Modules\Ai\Services\Product\ProductAiTranslationService;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Services\Translation\TranslationClientFactory;
use App\Modules\Ai\Support\ProductAiToolsPresenter;
use App\Modules\Catalog\Events\ProductCreated;
use App\Modules\Catalog\Events\ProductUpdated;
use App\Modules\Catalog\Events\Store\OwnProductSaved;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Events\StoreTranslationRunQueued;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\View\View as ViewInstance;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        StoreTranslationItemRepositoryContract::class => StoreTranslationItemRepository::class,
        AiUsageLogRepositoryContract::class => AiUsageLogRepository::class,
        AdminTranslationRunRepositoryContract::class => AdminTranslationRunRepository::class,
        ImageEmbeddingRepositoryContract::class => ImageEmbeddingRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        // Default client = custom translations provider (decision #44); paid runs ask the factory.
        $this->app->bind(TranslationClient::class, static fn ($app): TranslationClient => $app->make(TranslationClientFactory::class)->for(TranslationPurpose::Custom));
    }

    public function boot(): void
    {
        parent::boot();

        // Paid full-store translation (decision #44): queued run → batch of chunk jobs.
        Event::listen(StoreTranslationRunQueued::class, StartStoreTranslation::class);

        // Image search index (P10): on-save embedding of central + store-own product images.
        Event::listen([ProductCreated::class, ProductUpdated::class, OwnProductSaved::class], IndexChangedProductImages::class);

        // VEN-012b Content & translations tab + "Translate with AI" modal of a product page.
        View::composer(['ai::vendor.product-translation.tab', 'ai::vendor.product-translation.modal', 'ai::vendor.product-translation.button'], static function (ViewInstance $view): void {
            $product = $view->getData()['product'] ?? null;

            if (! $product instanceof StoreProduct || isset($view->getData()['aiContent'])) {
                return;
            }

            $service = app(ProductAiTranslationService::class);
            $staff = auth('vendor')->user();
            $view->with([
                'aiLanguages' => $service->storeLanguages(),
                'aiContent' => $service->content($product),
                'aiCanManage' => $staff !== null && $staff->can('update', $product),
            ]);
        });

        // VEN-010b / VEN-010c product AI tools (OpenAI only): buttons + modals of a product page.
        View::composer(['ai::vendor.product-tools.buttons', 'ai::vendor.product-tools.modals'], static function (ViewInstance $view): void {
            if (array_key_exists('aiTools', $view->getData())) {
                return;
            }

            $product = $view->getData()['product'] ?? null;
            $view->with('aiTools', $product instanceof StoreProduct ? app(ProductAiToolsPresenter::class)->present($product) : null);
        });

        // VEN-034a AI logo generator card of Appearance › Logo (OpenAI images only).
        View::composer('ai::vendor.ai-logo.card', static function (ViewInstance $view): void {
            if (array_key_exists('aiLogo', $view->getData())) {
                return;
            }

            $staff = auth('vendor')->user();
            $available = OpenAiTools::configured() && tenancy()->initialized && $staff !== null && $staff->can('store.appearance.manage');
            $quota = $available ? app(LogoGeneratorService::class)->quota((int) tenant()?->getTenantKey()) : null;
            $view->with('aiLogo', $quota === null ? null : [
                'used_label' => AiLogoController::usedLabel($quota->used, $quota->max),
                'store_name' => (string) (tenant()?->name ?? ''),
                'styles' => LogoStyle::options(),
                'colors' => LogoColor::options(),
                'urls' => ['generate' => vendor_route('store.appearance.ai-logo.generate'), 'apply' => vendor_route('store.appearance.ai-logo.apply')],
            ]);
        });

        // ADM-091b / ADM-092a: latest admin AI translation run of a page (pass 'kind', optional 'locale').
        View::composer('ai::admin.translation.banner', static function (ViewInstance $view): void {
            if (array_key_exists('latest', $view->getData())) {
                return;
            }

            $kind = $view->getData()['kind'] ?? null;
            $locale = $view->getData()['locale'] ?? null;
            $run = $kind instanceof AdminTranslationKind
                ? app(AdminTranslationRunRepositoryContract::class)->latest($kind, is_string($locale) ? $locale : null)
                : null;
            $view->with('latest', $run !== null ? app(AdminTranslationRunService::class)->present($run) : null);
        });

        // Weekly delta auto-translation of every bought store language (decision #44).
        if ($this->app->runningInConsole()) {
            $this->commands([TranslateDeltasCommand::class, IndexProductImagesCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('ai:translate-deltas')->weeklyOn(0, '03:00')->name('ai:translate-deltas')->onOneServer()->withoutOverlapping();
        });

        // Live run progress for the store's staff who can see Settings › Languages.
        Broadcast::channel('tenant.{tenant}.translation-runs', static fn (mixed $user, string $tenant): bool => $user instanceof VendorStaff
            && tenancy()->initialized
            && (string) tenant()?->getTenantKey() === $tenant
            && $user->can('settings.languages.view'), ['guards' => ['vendor']]);

        // Admin AI translation runs (ADM-091b / ADM-092a): live progress for admins who see either page.
        Gate::policy(AdminTranslationRun::class, AdminTranslationRunPolicy::class);
        Broadcast::channel(AdminTranslationRunUpdated::CHANNEL, static fn (mixed $user): bool => $user instanceof Admin
            && ($user->can('localization.languages.view') || $user->can('localization.translations.view')), ['guards' => ['admin']]);
    }
}
