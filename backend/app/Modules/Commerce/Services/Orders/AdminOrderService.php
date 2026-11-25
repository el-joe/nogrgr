<?php

namespace App\Modules\Commerce\Services\Orders;

use App\Modules\Commerce\DTOs\Orders\CancelOrderData;
use App\Modules\Commerce\DTOs\Orders\FulfilmentUpdateData;
use App\Modules\Commerce\DTOs\Orders\OrderContext;
use App\Modules\Commerce\Enums\ActorType;
use App\Modules\Commerce\Enums\AttachmentKind;
use App\Modules\Commerce\Exceptions\CommerceException;
use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderAttachment;
use App\Modules\Commerce\Repositories\Contracts\OrderAdminNoteRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderAttachmentRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderPaymentRepositoryContract;
use App\Modules\Commerce\Repositories\Contracts\OrderRepositoryContract;
use App\Modules\Commerce\Support\TenantRunner;
use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Reporting\Models\OrderFact;
use App\Modules\Reporting\Repositories\Contracts\OrderFactRepositoryContract;
use App\Modules\Shipping\DTOs\DeliveryEstimate;
use App\Shared\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Admin order pages (ADM-020…022a): lists read the central order_facts, a detail opens
 * the store's own database (TenantRunner) and every change goes through the Commerce
 * order services in that tenant context, so the store's order stays the single source
 * and order_facts follows through the order events. Own-products orders are read-only.
 * Attachments (private `central` disk) and internal notes are platform records.
 */
class AdminOrderService extends BaseService
{
    public const DISK = 'central';

    public function __construct(
        private readonly OrderFactRepositoryContract $facts,
        private readonly OrderRepositoryContract $orders,
        private readonly OrderPaymentRepositoryContract $payments,
        private readonly OrderAttachmentRepositoryContract $attachments,
        private readonly OrderAdminNoteRepositoryContract $notes,
        private readonly OrderFulfilmentService $fulfilment,
        private readonly OrderCancellationService $cancellation,
        private readonly OrderFinanceService $finance,
        private readonly TenantRunner $tenants,
    ) {}

    public function fact(int $id): OrderFact
    {
        return $this->facts->findFact($id) ?? throw CommerceException::rule('order_not_found', 404);
    }

    public function context(OrderFact $fact): OrderContext
    {
        $order = $this->tenants->run($fact->tenant_id, fn (): ?Order => $this->orders->findDetailed($fact->order_uuid));

        if ($order === null) {
            throw CommerceException::rule('order_not_found', 404);
        }

        $tenant = $fact->getRelationValue('tenant');

        return new OrderContext($fact->tenant_id, $order, $tenant instanceof Tenant ? $tenant->name : null, $tenant instanceof Tenant ? $tenant->subdomainHost() : null);
    }

    /**
     * Everything the detail page shows besides the order itself.
     *
     * @return array<string, mixed>
     */
    public function detail(OrderContext $context): array
    {
        $order = $context->order;

        return $this->tenants->run($context->tenantId, fn (): array => [
            'figures' => $this->finance->figures($order, $context->tenantId),
            'remaining' => $this->finance->remaining($order, $context->tenantId),
            'cancelPreview' => $this->finance->cancellationPreview($order, $context->tenantId),
            'canCancel' => $this->cancellation->canCancel($order),
            'payment' => $this->payments->paidForGroup($order->checkout_group),
            'attempts' => $this->payments->forGroup($order->checkout_group),
            'customerOrders' => $order->customer_id !== null ? $this->orders->placedCountForCustomer($order->customer_id) : 0,
            'attachments' => $this->attachments->forOrder($context->tenantId, $order->uuid),
            'notes' => $this->notes->forOrder($context->tenantId, $order->uuid),
        ]);
    }

    public function cancel(OrderContext $context, CancelOrderData $data): Order
    {
        $this->fulfilment->assertPlatformManageable($context->order);

        return $this->tenants->run($context->tenantId, fn (): Order => $this->cancellation->cancel($context->order, $data));
    }

    public function updateFulfilment(OrderContext $context, FulfilmentUpdateData $data): Order
    {
        $this->fulfilment->assertPlatformManageable($context->order);

        return $this->tenants->run($context->tenantId, fn (): Order => $this->fulfilment->update($context->order, $data));
    }

    public function previewCompany(OrderContext $context, int $companyId): ?DeliveryEstimate
    {
        return $this->tenants->run($context->tenantId, fn (): ?DeliveryEstimate => $this->fulfilment->previewEstimate($context->order, $companyId));
    }

    public function reassignCompany(OrderContext $context, int $companyId, Admin $admin): DeliveryEstimate
    {
        $this->fulfilment->assertPlatformManageable($context->order);

        return $this->tenants->run($context->tenantId, fn (): DeliveryEstimate => $this->fulfilment->reassignCompany($context->order, $companyId, ActorType::Admin, $admin->id));
    }

    public function addNote(OrderContext $context, string $note, Admin $admin): void
    {
        $this->notes->create(['tenant_id' => $context->tenantId, 'order_uuid' => $context->order->uuid, 'note' => $note, 'admin_id' => $admin->id, 'admin_name' => $admin->name]);
    }

    public function attach(OrderContext $context, UploadedFile $file, AttachmentKind $kind, Admin $admin): OrderAttachment
    {
        $this->fulfilment->assertPlatformManageable($context->order);
        $path = $file->store('orders/'.$context->tenantId.'/'.$context->order->uuid, self::DISK);

        if ($path === false) {
            throw CommerceException::rule('upload_failed');
        }

        /** @var OrderAttachment $attachment */
        $attachment = $this->attachments->create([
            'tenant_id' => $context->tenantId,
            'order_uuid' => $context->order->uuid,
            'kind' => $kind,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by_id' => $admin->id,
            'uploaded_by_name' => $admin->name,
        ]);

        return $attachment;
    }

    public function attachment(OrderFact $fact, int $id): OrderAttachment
    {
        $attachment = $this->attachments->findForOrder($fact->tenant_id, $fact->order_uuid, $id);

        if ($attachment === null || ! Storage::disk($attachment->disk)->exists($attachment->path)) {
            throw CommerceException::rule('file_not_found', 404);
        }

        return $attachment;
    }

    public function deleteAttachment(OrderFact $fact, int $id): void
    {
        $attachment = $this->attachments->findForOrder($fact->tenant_id, $fact->order_uuid, $id) ?? throw CommerceException::rule('file_not_found', 404);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $this->attachments->delete($attachment);
    }
}
