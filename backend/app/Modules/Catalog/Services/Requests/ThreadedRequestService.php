<?php

namespace App\Modules\Catalog\Services\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\BrandRequestStatus;
use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Enums\ProductRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Enums\RequestPriority;
use App\Modules\Catalog\Events\Requests\RequestStatusChanged;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Support\ThreadedRequestRepositories;
use App\Shared\Services\BaseService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Admin actions on product / manufacturing / brand requests: lookup, status, priority,
 * archive (product requests) and internal notes. Each change pushes a live refresh
 * and fires RequestStatusChanged (→ vendor notification).
 */
class ThreadedRequestService extends BaseService
{
    public function __construct(
        private readonly ThreadedRequestRepositories $requests,
        private readonly RequestThreadService $threads,
    ) {}

    public function findOrFail(RequestKind $kind, int $id): Model&ThreadedRequest
    {
        return $this->requests->find($kind, $id) ?? throw new NotFoundHttpException;
    }

    public function adminUnreadCount(RequestKind $kind): int
    {
        return $this->requests->adminUnreadCount($kind);
    }

    /**
     * Status enum cases of a kind (for selects / modals).
     *
     * @return array<string, string>
     */
    public function statusOptions(RequestKind $kind): array
    {
        /** @var array<string, string> $options */
        $options = match ($kind) {
            RequestKind::Product => ProductRequestStatus::options(),
            RequestKind::Manufacturing => ManufacturingRequestStatus::options(),
            RequestKind::Brand => BrandRequestStatus::options(),
        };

        return $options;
    }

    public function changeStatus(Model&ThreadedRequest $request, string $status): void
    {
        $next = match ($request->kind()) {
            RequestKind::Product => ProductRequestStatus::from($status),
            RequestKind::Manufacturing => ManufacturingRequestStatus::from($status),
            RequestKind::Brand => BrandRequestStatus::from($status),
        };
        /** @var BackedEnum $current */
        $current = $request->getAttribute('status');

        if ($current === $next) {
            return;
        }

        $this->requests->update($request, ['status' => $next]);
        event(new RequestStatusChanged($request, (string) $current->value, (string) $next->value));
        $this->threads->pushUpdate($request, 'status');
    }

    public function changePriority(ProductRequest $request, RequestPriority $priority): void
    {
        $this->requests->update($request, ['priority' => $priority]);
        $this->threads->pushUpdate($request, 'priority');
    }

    public function archive(ProductRequest $request, bool $archived): void
    {
        if ($request->isArchived() === $archived) {
            return;
        }

        $this->requests->update($request, ['archived_at' => $archived ? now() : null]);
        event(new RequestStatusChanged($request, $request->status->value, $archived ? 'archived' : 'restored'));
        $this->threads->pushUpdate($request, 'status');
    }

    /**
     * Internal (admin-only) notes of manufacturing / brand requests.
     */
    public function saveNotes(Model&ThreadedRequest $request, ?string $notes): void
    {
        $this->requests->update($request, ['admin_notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null]);
    }
}
