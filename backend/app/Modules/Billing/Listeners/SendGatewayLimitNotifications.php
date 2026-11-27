<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Notifications\BillingAlertNotification;
use App\Modules\Billing\Services\Finance\StoreFinanceNotifier;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Events\GatewayLimitBlocked;
use App\Modules\Integrations\Events\GatewayLimitWarning;
use App\Modules\Integrations\Models\GatewayLimitUsage;
use App\Modules\Integrations\Services\Limits\GatewayLimitService;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Money\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;

/**
 * Gateway limit warning (MAIL-023) / block (MAIL-024), decision #124: admins with
 * integrations.view get `admin.gateway_limit_warning` / `admin.gateway_limit_block`
 * (inbox + mail + Reverb), the store owners `vendor.gateway_limit` (inbox + Reverb + mail).
 */
class SendGatewayLimitNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
        private readonly TenantRepositoryContract $tenants,
        private readonly StoreFinanceNotifier $finance,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(GatewayLimitWarning::class, [self::class, 'warning']);
        $events->listen(GatewayLimitBlocked::class, [self::class, 'blocked']);
    }

    public function warning(GatewayLimitWarning $event): void
    {
        $this->send($event->usage, $event->limitMinor, 'gateway_limit_warning');
    }

    public function blocked(GatewayLimitBlocked $event): void
    {
        $this->send($event->usage, $event->limitMinor, 'gateway_limit_block');
    }

    private function send(GatewayLimitUsage $usage, int $limitMinor, string $name): void
    {
        $tenant = $this->tenants->find($usage->tenant_id);
        $params = [
            'store' => (string) ($tenant?->getAttribute('name') ?? '—'),
            'gateway' => PaymentGatewayCode::tryFrom($usage->gateway)?->brand() ?? $usage->gateway,
            'volume' => Money::ofMinor($usage->volume_minor)->format(),
            'limit' => Money::ofMinor($limitMinor)->format(),
            'percent' => (int) GatewayLimitService::percentOf($usage->volume_minor, $limitMinor),
        ];

        $admins = $this->admins->activeWithPermission('integrations.view');

        if ($admins->isNotEmpty()) {
            $this->dispatcher->send($admins, new BillingAlertNotification(NotificationCategory::Payments, $name, $params, rescue(static fn (): string => route('admin.integrations.gateways.limits'), null, false)));
        }

        $this->finance->notify($usage->tenant_id, $name, $params, 'settings.payment-gateways.index');
    }
}
