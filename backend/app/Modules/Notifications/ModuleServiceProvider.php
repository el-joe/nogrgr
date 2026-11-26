<?php

namespace App\Modules\Notifications;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Contracts\HasPanelInbox;
use App\Modules\Notifications\Events\EmailTemplatesSynced;
use App\Modules\Notifications\Listeners\SyncStoreEmailTemplates;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\NotificationPreferenceRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\PanelNotificationRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\StoreEmailTemplateRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\TenantNotificationRepositoryContract;
use App\Modules\Notifications\Repositories\Eloquent\EmailTemplateRepository;
use App\Modules\Notifications\Repositories\Eloquent\NotificationPreferenceRepository;
use App\Modules\Notifications\Repositories\Eloquent\PanelNotificationRepository;
use App\Modules\Notifications\Repositories\Eloquent\StoreEmailTemplateRepository;
use App\Modules\Notifications\Repositories\Eloquent\TenantNotificationRepository;
use App\Modules\Notifications\Support\StoreAwareMailManager;
use App\Modules\Notifications\Support\StoreMailerResolver;
use App\Modules\Notifications\Support\SurfacePanelNotificationFeed;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Shared\Navigation\Contracts\PanelNotificationFeed;
use App\Shared\Routing\ModuleRouteRegistry;
use App\Shared\Routing\Surface;
use Illuminate\Database\Eloquent\Model;
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
        PanelNotificationRepositoryContract::class => PanelNotificationRepository::class,
        NotificationPreferenceRepositoryContract::class => NotificationPreferenceRepository::class,
        PanelNotificationFeed::class => SurfacePanelNotificationFeed::class,
        TenantNotificationRepositoryContract::class => TenantNotificationRepository::class,
        EmailTemplateRepositoryContract::class => EmailTemplateRepository::class,
        StoreEmailTemplateRepositoryContract::class => StoreEmailTemplateRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        // A store's e-mails go through its own SMTP when it has one (VEN-081, decision #91).
        $this->app->scoped(StoreMailerResolver::class);
        $this->app->extend('mail.manager', static fn (mixed $manager, $app): StoreAwareMailManager => new StoreAwareMailManager($app));
    }

    public function boot(): void
    {
        parent::boot();

        // Vendor › Settings › Email templates + Mail (VEN-080 / 081): own route file next to routes/vendor.php.
        $this->app->make(ModuleRouteRegistry::class)->add(Surface::Vendor, $this->modulePath('routes/vendor-settings.php'));

        // Platform "Sync to tenants" → every store's copy of the shopper templates.
        Event::listen(EmailTemplatesSynced::class, SyncStoreEmailTemplates::class);

        $this->bootChannels();
    }

    /**
     * Private live channels of central panel users: "admin.{id}" (docs/plan/02-architecture.md).
     */
    private function bootChannels(): void
    {
        Broadcast::channel('admin.{id}', static fn (mixed $user, string $id): bool => $user instanceof HasPanelInbox
            && $user instanceof Model
            && $user->panelSurface() === 'admin'
            && (string) $user->getKey() === $id, ['guards' => ['admin']]);

        // Vendor staff: one private channel per store and member (ids repeat across tenant DBs).
        Broadcast::channel('tenant.{tenant}.vendor.{id}', static fn (mixed $user, string $tenant, string $id): bool => $user instanceof VendorStaff
            && tenancy()->initialized
            && (string) tenant()?->getTenantKey() === $tenant
            && (string) $user->getKey() === $id, ['guards' => ['vendor']]);
    }
}
