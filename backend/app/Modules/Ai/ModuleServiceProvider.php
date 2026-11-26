<?php

namespace App\Modules\Ai;

use App\Modules\Ai\Contracts\TranslationClient;
use App\Modules\Ai\Enums\TranslationPurpose;
use App\Modules\Ai\Listeners\StartStoreTranslation;
use App\Modules\Ai\Repositories\Contracts\StoreTranslationItemRepositoryContract;
use App\Modules\Ai\Repositories\Eloquent\StoreTranslationItemRepository;
use App\Modules\Ai\Services\Translation\TranslationClientFactory;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Events\StoreTranslationRunQueued;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        StoreTranslationItemRepositoryContract::class => StoreTranslationItemRepository::class,
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

        // Live run progress for the store's staff who can see Settings › Languages.
        Broadcast::channel('tenant.{tenant}.translation-runs', static fn (mixed $user, string $tenant): bool => $user instanceof VendorStaff
            && tenancy()->initialized
            && (string) tenant()?->getTenantKey() === $tenant
            && $user->can('settings.languages.view'), ['guards' => ['vendor']]);
    }
}
