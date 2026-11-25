<?php

namespace App\Modules\Catalog\Services\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\DTOs\Requests\RequestReplyData;
use App\Modules\Catalog\DTOs\Requests\VendorRequestSubmissionData;
use App\Modules\Catalog\Enums\BrandRequestStatus;
use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Enums\MessageSender;
use App\Modules\Catalog\Enums\ProductRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Events\Requests\RequestCancelledByVendor;
use App\Modules\Catalog\Events\Requests\RequestSubmitted;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Support\ThreadedRequestRepositories;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Models\Tenant;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Vendor side of the Requests center (VEN-050…050j): the store's own product /
 * manufacturing / brand requests — hub tabs with unread badges, lists and stats, submit
 * (opening message carries the notes' files), open (platform messages read), cancel a
 * manufacturing request, delete a pending brand request. Every lookup is limited to the
 * current store (the shared repositories read without the tenant scope).
 */
class VendorRequestService extends BaseService
{
    public function __construct(
        private readonly ThreadedRequestRepositories $requests,
        private readonly RequestThreadService $threads,
    ) {}

    public static function tenantId(): int
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            throw new LogicException('Vendor requests need an initialized tenant.');
        }

        return (int) $tenant->getTenantKey();
    }

    /**
     * Hub tabs the staff member may open, with the requests carrying unread replies.
     *
     * @return array<string, array{label: string, href: string, count: int}>
     */
    public function tabs(VendorStaff $staff): array
    {
        $tabs = [];

        foreach (RequestKind::cases() as $kind) {
            if ($staff->can($kind->permission('view'))) {
                $tabs[$kind->value] = [
                    'label' => (string) __('catalog::vendor_requests.tabs.'.$kind->value),
                    'href' => vendor_route('requests.'.$kind->value.'.index'),
                    'count' => $this->unreadCount($kind),
                ];
            }
        }

        return $tabs;
    }

    public function firstRoute(VendorStaff $staff): ?string
    {
        foreach (RequestKind::cases() as $kind) {
            if ($staff->can($kind->permission('view'))) {
                return vendor_route('requests.'.$kind->value.'.index');
            }
        }

        return null;
    }

    /**
     * Sidebar badges: "vendor-requests" (total) and "vendor-requests.<kind>".
     *
     * @return array<string, int>
     */
    public function badgeCounts(VendorStaff $staff): array
    {
        $counts = [];
        $total = 0;

        foreach (RequestKind::cases() as $kind) {
            if ($staff->can($kind->permission('view'))) {
                $counts['vendor-requests.'.$kind->value] = $count = $this->unreadCount($kind);
                $total += $count;
            }
        }

        return $counts === [] ? [] : ['vendor-requests' => $total, ...$counts];
    }

    public function unreadCount(RequestKind $kind): int
    {
        return $this->requests->for($kind)->vendorUnreadCount(self::tenantId());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ProductRequest>|Builder<ManufacturingRequest>|Builder<BrandRequest>
     */
    public function listing(RequestKind $kind, array $filters = []): Builder
    {
        return $this->requests->for($kind)->vendorListing(self::tenantId(), $filters);
    }

    /**
     * @return array{total: int, open: int, completed: int}
     */
    public function stats(RequestKind $kind): array
    {
        return $this->requests->for($kind)->vendorStats(self::tenantId());
    }

    /**
     * CSV export of the store's manufacturing requests (VEN-050d) with the list filters.
     *
     * @param  resource  $handle
     * @param  array<string, mixed>  $filters
     */
    public function exportManufacturing($handle, array $filters = []): void
    {
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, array_map(static fn (string $column): string => (string) __('catalog::vendor_requests.export.'.$column), ['reference', 'product', 'quantity', 'status', 'created', 'updated']));

        foreach ($this->listing(RequestKind::Manufacturing, $filters)->lazyByIdDesc(500, 'catalog_manufacturing_requests.id', 'id') as $request) {
            /** @var ManufacturingRequest $request */
            fputcsv($handle, [
                $request->reference(),
                $request->product_name,
                $request->quantity,
                $request->statusLabel(),
                $request->created_at?->toDateTimeString(),
                ($request->last_message_at ?? $request->updated_at)?->toDateTimeString(),
            ]);
        }
    }

    public function findOwned(RequestKind $kind, int $id): Model&ThreadedRequest
    {
        return $this->requests->findForTenant($kind, self::tenantId(), $id) ?? throw new NotFoundHttpException;
    }

    public function submit(VendorRequestSubmissionData $data): Model&ThreadedRequest
    {
        $notes = $data->notes !== null && trim($data->notes) !== '' ? trim($data->notes) : null;

        $attributes = match ($data->kind) {
            RequestKind::Product => [
                'title' => $data->subject,
                'description' => $notes ?? '',
                'product_url' => $data->url,
                'target_country_ids' => $data->countryIds === [] ? null : $data->countryIds,
                'status' => ProductRequestStatus::Pending,
            ],
            RequestKind::Manufacturing => [
                'product_name' => $data->subject,
                'description' => $notes,
                'product_id' => $data->productId,
                'quantity' => max(1, (int) $data->quantity),
                'status' => ManufacturingRequestStatus::Requested,
            ],
            RequestKind::Brand => [
                'brand_name' => $data->subject,
                'description' => $notes,
                'website' => $data->url,
                'category_id' => $data->categoryId,
                'target_country_ids' => $data->countryIds === [] ? null : $data->countryIds,
                'status' => BrandRequestStatus::Pending,
            ],
        };

        $request = $this->transaction(function () use ($data, $attributes): Model&ThreadedRequest {
            $request = $this->requests->create($data->kind, [
                ...$attributes,
                'tenant_id' => $data->tenantId,
                'submitted_by' => $data->submittedBy,
                'last_message_at' => $data->files !== [] ? now() : null,
            ]);
            $this->dispatchAfterCommit(new RequestSubmitted($request));

            return $request;
        }, (new ($data->kind->modelClass()))->getConnectionName());

        if ($data->files !== []) {
            $this->threads->post($request, new RequestReplyData(
                sender: MessageSender::Vendor,
                senderName: $data->senderName,
                body: null,
                files: $data->files,
                silent: true,
            ));
        }

        return $request;
    }

    public function open(Model&ThreadedRequest $request): void
    {
        $this->threads->openByVendor($request);
    }

    /**
     * The store cancels a manufacturing request that is not in production yet.
     */
    public function cancel(ManufacturingRequest $request): void
    {
        if (! $request->status->isCancellableByVendor()) {
            throw RequestRuleException::notCancellable();
        }

        $this->requests->update($request, ['status' => ManufacturingRequestStatus::Cancelled]);
        event(new RequestCancelledByVendor($request));
        $this->threads->pushUpdate($request, 'status');
    }

    /**
     * A brand request may be withdrawn while pending and without payment requests.
     */
    public function delete(BrandRequest $request): void
    {
        if ($request->status !== BrandRequestStatus::Pending || $request->payments()->exists()) {
            throw RequestRuleException::notDeletable();
        }

        $this->transaction(function () use ($request): void {
            $this->threads->purge($request);
            $this->requests->for(RequestKind::Brand)->delete($request);
        }, $request->getConnectionName());
    }
}
