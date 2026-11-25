<?php

namespace App\Modules\Catalog\Listeners\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Events\Requests\EditRequestReviewed;
use App\Modules\Catalog\Events\Requests\RequestCancelledByVendor;
use App\Modules\Catalog\Events\Requests\RequestMessagePosted;
use App\Modules\Catalog\Events\Requests\RequestPaymentRequested;
use App\Modules\Catalog\Events\Requests\RequestStatusChanged;
use App\Modules\Catalog\Events\Requests\RequestSubmitted;
use App\Modules\Catalog\Events\Requests\TenantChangeReviewed;
use App\Modules\Catalog\Jobs\Requests\NotifyStoreStaff;
use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\OwnerRepositoryContract;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Requests-center events → notifications (shared Notifications module):
 *  - admin reply / status change / payment request / review decision → store owner
 *    (owner account: database + mail + Reverb per preferences; else e-mail to owner_email);
 *  - vendor reply / new request / cancellation → admins allowed to manage that request type;
 *  - every store-facing update also reaches the store's own staff (vendor bell, inbox and
 *    live push) through the queued NotifyStoreStaff job.
 */
class SendRequestNotifications
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
        private readonly TenantRepositoryContract $tenants,
        private readonly OwnerRepositoryContract $owners,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(RequestMessagePosted::class, [self::class, 'messagePosted']);
        $events->listen(RequestStatusChanged::class, [self::class, 'statusChanged']);
        $events->listen(RequestPaymentRequested::class, [self::class, 'paymentRequested']);
        $events->listen(RequestSubmitted::class, [self::class, 'submitted']);
        $events->listen(RequestCancelledByVendor::class, [self::class, 'cancelledByVendor']);
        $events->listen(EditRequestReviewed::class, [self::class, 'editReviewed']);
        $events->listen(TenantChangeReviewed::class, [self::class, 'tenantChangeReviewed']);
    }

    public function messagePosted(RequestMessagePosted $event): void
    {
        $request = $event->request;
        $params = $this->threadParams($request) + ['excerpt' => Str::limit((string) $event->message->body, 120)];

        if ($event->message->sender === MessageSender::Vendor) {
            $this->notifyAdmins($request, 'vendor_replied', $params);

            return;
        }

        $this->notifyOwner($request->tenantId(), 'admin_replied', $params);
        $this->notifyStaff($request, 'admin_replied', $params);
    }

    public function submitted(RequestSubmitted $event): void
    {
        $this->notifyAdmins($event->request, 'vendor_submitted', $this->threadParams($event->request));
    }

    public function cancelledByVendor(RequestCancelledByVendor $event): void
    {
        $this->notifyAdmins($event->request, 'vendor_cancelled', $this->threadParams($event->request));
    }

    public function statusChanged(RequestStatusChanged $event): void
    {
        $params = $this->threadParams($event->request) + ['status' => $this->statusLabel($event)];

        $this->notifyOwner($event->request->tenantId(), 'status_changed', $params);
        $this->notifyStaff($event->request, 'status_changed', $params);
    }

    public function paymentRequested(RequestPaymentRequested $event): void
    {
        $params = $this->threadParams($event->request) + [
            'label' => $event->payment->label,
            'amount' => $event->payment->amount->format(),
        ];

        $this->notifyOwner($event->request->tenantId(), $event->cancelled ? 'payment_cancelled' : 'payment_requested', $params);
        $this->notifyStaff($event->request, $event->cancelled ? 'payment_cancelled' : 'payment_requested', $params);
    }

    public function editReviewed(EditRequestReviewed $event): void
    {
        $editRequest = $event->editRequest;
        $product = $editRequest->product;

        $params = [
            'reference' => $editRequest->reference(),
            'product' => $product !== null ? (string) $product->name : '',
            'reason' => (string) $editRequest->reason,
        ];

        $this->notifyOwner($editRequest->tenant_id, 'edit_'.$editRequest->status->value, $params);
        NotifyStoreStaff::dispatch($editRequest->tenant_id, 'catalog.products.view', 'edit_'.$editRequest->status->value, $params, 'catalog.edit-requests.index');
    }

    public function tenantChangeReviewed(TenantChangeReviewed $event): void
    {
        $change = $event->changeRequest;

        $params = [
            'type' => $change->type->label(),
            'note' => (string) $change->admin_note,
        ];

        $this->notifyOwner($change->tenant_id, 'tenant_change_'.$change->status->value, $params);

        $tenant = $this->tenants->find($change->tenant_id);
        NotifyStoreStaff::dispatch($change->tenant_id, 'requests.tenant-changes.view', 'tenant_change_'.$change->status->value, $params + ['store' => $tenant instanceof Tenant ? (string) $tenant->name : ''], 'requests.tenant-changes.index');
    }

    /**
     * @param  array<string, string|int>  $params
     */
    private function notifyAdmins(Model&ThreadedRequest $request, string $event, array $params): void
    {
        // Vendor events fire inside the store's tenant context: admin roles and permissions
        // live in the central database, so the lookup runs centrally.
        $send = function () use ($request, $event, $params): void {
            $admins = $this->admins->activeWithPermission($request->kind()->permission('manage'));

            if ($admins->isEmpty()) {
                return;
            }

            $tenant = $this->tenants->find($request->tenantId());
            $params['store'] = $tenant instanceof Tenant ? (string) $tenant->name : '';

            $this->dispatcher->send($admins, new RequestUpdateNotification($event, $params, route($request->kind()->routePrefix().'.show', $request->getKey())));
        };

        tenancy()->initialized ? tenancy()->central($send) : $send();
    }

    /**
     * @param  array<string, string|int>  $params
     */
    private function notifyStaff(Model&ThreadedRequest $request, string $event, array $params): void
    {
        $tenant = $this->tenants->find($request->tenantId());
        $params['store'] = $tenant instanceof Tenant ? (string) $tenant->name : '';

        NotifyStoreStaff::dispatch(
            $request->tenantId(),
            $request->kind()->permission('view'),
            $event,
            $params,
            'requests.'.$request->kind()->value.'.show',
            ['id' => (int) $request->getKey()],
        );
    }

    /**
     * @return array<string, string|int>
     */
    private function threadParams(Model&ThreadedRequest $request): array
    {
        return ['reference' => $request->reference(), 'subject' => $request->subject(), 'kind' => $request->kind()->label()];
    }

    private function statusLabel(RequestStatusChanged $event): string
    {
        if (in_array($event->to, ['archived', 'restored'], true)) {
            return (string) __('catalog::requests.states.'.$event->to);
        }

        return $event->request->statusLabel();
    }

    /**
     * @param  array<string, string|int>  $params
     */
    private function notifyOwner(int $tenantId, string $event, array $params): void
    {
        $tenant = $this->tenants->find($tenantId);

        if (! $tenant instanceof Tenant) {
            return;
        }

        $params['store'] = $tenant->name;
        $notification = new RequestUpdateNotification($event, $params, route('central.tenant.login'));
        $owner = $tenant->owner_id !== null ? $this->owners->find($tenant->owner_id) : null;

        if ($owner instanceof Owner) {
            $this->dispatcher->send($owner, $notification);

            return;
        }

        if (filled($tenant->owner_email)) {
            Notification::route('mail', (string) $tenant->owner_email)->notify($notification);
        }
    }
}
