<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Notifications\BillingAlertNotification;
use App\Modules\Billing\Services\Finance\StoreFinanceNotifier;
use App\Modules\Commerce\Events\SettlementPaid;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * SettlementPaid → receipts (MAIL-027 / MAIL-028, decision #124): store owners get the
 * `vendor.settlement_receipt` (inbox + Reverb + mail) and admins with
 * finance.settlements.view the "settlement received" alert. Manual settlements recorded
 * by an admin already send the `settlement_confirmed` receipt (ManualSettlementService)
 * and are skipped here.
 */
class SendSettlementReceipt implements ShouldQueue
{
    public string $queue = 'notifications';

    public bool $afterCommit = true;

    public function __construct(
        private readonly StoreFinanceNotifier $notifier,
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
        private readonly TenantRepositoryContract $tenants,
    ) {}

    public function handle(SettlementPaid $event): void
    {
        $settlement = $event->settlement;

        if ($settlement->gateway === 'manual') {
            return;
        }

        $params = [
            'reference' => (string) ($settlement->reference ?? $settlement->id),
            'amount' => $settlement->money()->format(),
            'order' => $settlement->order_number,
        ];

        $this->notifier->notify($settlement->tenant_id, 'settlement_paid', $params, 'finance.settlements.index');

        $admins = $this->admins->activeWithPermission('finance.settlements.view');

        if ($admins->isNotEmpty()) {
            $store = $this->tenants->find($settlement->tenant_id)?->getAttribute('name') ?? '—';
            $this->dispatcher->send($admins, new BillingAlertNotification(
                NotificationCategory::Payments,
                'settlement_received',
                $params + ['store' => (string) $store],
                rescue(static fn (): string => route('admin.finance.settlements.index'), null, false),
            ));
        }
    }
}
