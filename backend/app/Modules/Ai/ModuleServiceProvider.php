<?php

namespace App\Modules\Ai;

use App\Modules\Ai\Console\TranslateDeltasCommand;
use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\Enums\TranslationPurpose;
use App\Modules\Ai\Listeners\StartStoreTranslation;
use App\Modules\Ai\Repositories\Contracts\AiUsageLogRepositoryContract;
use App\Modules\Ai\Repositories\Contracts\StoreTranslationItemRepositoryContract;
use App\Modules\Ai\Repositories\Eloquent\AiUsageLogRepository;
use App\Modules\Ai\Repositories\Eloquent\StoreTranslationItemRepository;
use App\Modules\Ai\Services\Product\ProductAiTranslationService;
use App\Modules\Ai\Services\Translation\TranslationClientFactory;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Events\StoreTranslationRunQueued;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
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

        // VEN-012b Content & translations tab + "Translate with AI" modal of a product page.
        View::composer(['ai::vendor.product-translation.tab', 'ai::vendor.product-translation.modal'], static function (ViewInstance $view): void {
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

        // Weekly delta auto-translation of every bought store language (decision #44).
        if ($this->app->runningInConsole()) {
            $this->commands([TranslateDeltasCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('ai:translate-deltas')->weeklyOn(0, '03:00')->name('ai:translate-deltas')->onOneServer()->withoutOverlapping();
        });

        // Live run progress for the store's staff who can see Settings › Languages.
        Broadcast::channel('tenant.{tenant}.translation-runs', static fn (mixed $user, string $tenant): bool => $user instanceof VendorStaff
            && tenancy()->initialized
            && (string) tenant()?->getTenantKey() === $tenant
            && $user->can('settings.languages.view'), ['guards' => ['vendor']]);
    }
}
