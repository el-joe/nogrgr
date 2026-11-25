<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Repositories\Contracts\ThreadedRequestRepositoryContract;
use App\Modules\Catalog\Services\Requests\VendorRequestService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Requests lists (VEN-050a product, 050d manufacturing, 050g brand): the store's
 * own requests of one kind, server-side. Filters: status ("open" or a value), search.
 * One subclass per kind names its repository; for() picks the right one.
 *
 * @template TModel of Model&ThreadedRequest
 *
 * @extends BaseDataTable<TModel>
 */
abstract class VendorRequestsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'search'];

    abstract protected function kind(): RequestKind;

    /**
     * @return ThreadedRequestRepositoryContract<TModel>
     */
    abstract protected function repository(): ThreadedRequestRepositoryContract;

    /**
     * @return VendorRequestsDataTable<covariant Model&ThreadedRequest>
     */
    public static function for(RequestKind $kind): self
    {
        return match ($kind) {
            RequestKind::Product => app(VendorProductRequestsDataTable::class),
            RequestKind::Manufacturing => app(VendorManufacturingRequestsDataTable::class),
            RequestKind::Brand => app(VendorBrandRequestsDataTable::class),
        };
    }

    protected function query(): Builder
    {
        return $this->repository()->vendorListing(VendorRequestService::tenantId());
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->repository()->vendorListing(VendorRequestService::tenantId(), $filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $kind = $this->kind();
        $prefix = $this->repository()->query()->getModel()->getTable();
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        $table
            ->filter(static fn (): null => null)
            ->addColumn('request', static fn (Model&ThreadedRequest $request): string => view('catalog::vendor.requests._request-cell', ['request' => $request])->render())
            ->editColumn('status', static fn (Model&ThreadedRequest $request): string => view('catalog::admin.requests.partials._badge', ['tone' => $request->statusTone(), 'label' => $request->statusLabel()])->render())
            ->addColumn('unread', static fn (Model $request): string => view('catalog::vendor.requests._unread-cell', ['count' => (int) $request->getAttribute('vendor_unread_count')])->render())
            ->addColumn('updated', static fn (Model $request): string => view('catalog::admin.requests.partials._date-cell', ['date' => $request->getAttribute('last_message_at') ?? $request->getAttribute('updated_at')])->render())
            ->addColumn('actions', static fn (Model&ThreadedRequest $request): string => view('catalog::vendor.requests._actions-cell', ['request' => $request])->render())
            ->orderColumn('request', static fn (Builder $query, string $order) => $query->orderBy($prefix.'.id', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy($prefix.'.status', $direction($order)))
            ->orderColumn('unread', static fn (Builder $query, string $order) => $query->orderBy('vendor_unread_count', $direction($order)))
            ->orderColumn('updated', static fn (Builder $query, string $order) => $query->orderByRaw('COALESCE('.$prefix.'.last_message_at, '.$prefix.'.updated_at) '.$direction($order)));

        $columns = ['id', 'request', 'status', 'unread', 'updated', 'actions'];

        if ($kind === RequestKind::Manufacturing) {
            $table->addColumn('quantity', static fn (Model $request): string => $request instanceof ManufacturingRequest ? number_format($request->quantity) : '')
                ->orderColumn('quantity', static fn (Builder $query, string $order) => $query->orderBy($prefix.'.quantity', $direction($order)));
            $columns[] = 'quantity';
        }

        return $table->rawColumns(['request', 'status', 'unread', 'updated', 'actions'])->only($columns);
    }
}
