<?php

namespace App\Modules\Notifications;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\Contracts\HasPanelInbox;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\NotificationPreferenceRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\PanelNotificationRepositoryContract;
use App\Modules\Notifications\Repositories\Contracts\TenantNotificationRepositoryContract;
use App\Modules\Notifications\Repositories\Eloquent\EmailTemplateRepository;
use App\Modules\Notifications\Repositories\Eloquent\NotificationPreferenceRepository;
use App\Modules\Notifications\Repositories\Eloquent\PanelNotificationRepository;
use App\Modules\Notifications\Repositories\Eloquent\TenantNotificationRepository;
use App\Modules\Notifications\Support\SurfacePanelNotificationFeed;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Shared\Navigation\Contracts\PanelNotificationFeed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Broadcast;

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
    ];

    public function boot(): void
    {
        parent::boot();

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
