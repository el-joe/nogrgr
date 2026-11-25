<?php

namespace App\Modules\Catalog\Listeners\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Events\Requests\EditRequestReviewed;
use App\Modules\Catalog\Events\Requests\RequestMessagePosted;
use App\Modules\Catalog\Events\Requests\RequestPaymentRequested;
use App\Modules\Catalog\Events\Requests\RequestStatusChanged;
use App\Modules\Catalog\Events\Requests\TenantChangeReviewed;
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
 *  - vendor reply → admins allowed to manage that request type.
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
        $events->listen(EditRequestReviewed::class, [self::class, 'editReviewed']);
        $events->listen(TenantChangeReviewed::class, [self::class, 'tenantChangeReviewed']);
    }

    public function messagePosted(RequestMessagePosted $event): void
    {
        $request = $event->request;
        $params = $this->threadParams($request) + ['excerpt' => Str::limit((string) $event->message->body, 120)];

        if ($event->message->sender === MessageSender::Vendor) {
            $admins = $this->admins->activeWithPermission($request->kind()->permission('manage'));

            if ($admins->isNotEmpty()) {
                $this->dispatcher->send($admins, new RequestUpdateNotification('vendor_replied', $params, route($request->kind()->routePrefix().'.show', $request->getKey())));
            }

            return;
        }

        $this->notifyOwner($request->tenantId(), 'admin_replied', $params);
    }

    public function statusChanged(RequestStatusChanged $event): void
    {
        $params = $this->threadParams($event->request) + ['status' => $this->statusLabel($event)];

        $this->notifyOwner($event->request->tenantId(), 'status_changed', $params);
    }

    public function paymentRequested(RequestPaymentRequested $event): void
    {
        $params = $this->threadParams($event->request) + [
            'label' => $event->payment->label,
            'amount' => $event->payment->amount->format(),
        ];

        $this->notifyOwner($event->request->tenantId(), $event->cancelled ? 'payment_cancelled' : 'payment_requested', $params);
    }

    public function editReviewed(EditRequestReviewed $event): void
    {
        $editRequest = $event->editRequest;
        $product = $editRequest->product;

        $this->notifyOwner($editRequest->tenant_id, 'edit_'.$editRequest->status->value, [
            'reference' => $editRequest->reference(),
            'product' => $product !== null ? (string) $product->name : '',
            'reason' => (string) $editRequest->reason,
        ]);
    }

    public function tenantChangeReviewed(TenantChangeReviewed $event): void
    {
        $change = $event->changeRequest;

        $this->notifyOwner($change->tenant_id, 'tenant_change_'.$change->status->value, [
            'type' => $change->type->label(),
            'note' => (string) $change->admin_note,
        ]);
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
