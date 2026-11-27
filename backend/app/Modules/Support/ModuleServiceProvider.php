<?php

namespace App\Modules\Support;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Support\Listeners\SendCustomerTicketNotifications;
use App\Modules\Support\Listeners\SendSupportNotifications;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Policies\SupportTicketPolicy;
use App\Modules\Support\Repositories\Contracts\CustomerTicketRepositoryContract;
use App\Modules\Support\Repositories\Contracts\HelpFeedbackRepositoryContract;
use App\Modules\Support\Repositories\Contracts\SupportTicketMessageRepositoryContract;
use App\Modules\Support\Repositories\Contracts\SupportTicketRepositoryContract;
use App\Modules\Support\Repositories\Eloquent\CustomerTicketRepository;
use App\Modules\Support\Repositories\Eloquent\HelpFeedbackRepository;
use App\Modules\Support\Repositories\Eloquent\SupportTicketMessageRepository;
use App\Modules\Support\Repositories\Eloquent\SupportTicketRepository;
use App\Modules\Support\Support\SupportBadgeCounter;
use App\Modules\Support\Support\VendorSupportBadgeCounter;
use App\Shared\Modules\ModuleServiceProvider as BaseModuleServiceProvider;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

/**
 * Support module (P9, decision #92): admin support tickets (ADM-116), vendor tickets and
 * help center (VEN-091 / 092), owner-portal tickets (OWN-009), the reply e-mail
 * (MAIL-037) and customer ↔ store tickets (store database, Vendor › Support › Customers).
 */
class ModuleServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Repository contract => Eloquent implementation.
     *
     * @var array<class-string, class-string>
     */
    public $bindings = [
        SupportTicketRepositoryContract::class => SupportTicketRepository::class,
        SupportTicketMessageRepositoryContract::class => SupportTicketMessageRepository::class,
        HelpFeedbackRepositoryContract::class => HelpFeedbackRepository::class,
        CustomerTicketRepositoryContract::class => CustomerTicketRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->tag([SupportBadgeCounter::class, VendorSupportBadgeCounter::class], PanelBadgeCounter::TAG);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Event::subscribe(SendSupportNotifications::class);
        Event::subscribe(SendCustomerTicketNotifications::class);

        // Live ticket thread: admins with support.tickets.view, and the store's own staff
        // with support.view (tenant scope limits the lookup to their store).
        Broadcast::channel('support.tickets.{id}', static function (mixed $user, string $id): bool {
            if (! ctype_digit($id)) {
                return false;
            }

            if ($user instanceof Admin) {
                return $user->can('support.tickets.view');
            }

            return $user instanceof VendorStaff
                && tenancy()->initialized
                && $user->can('support.view')
                && SupportTicket::query()->whereKey((int) $id)->exists();
        }, ['guards' => ['admin', 'vendor']]);
    }
}
