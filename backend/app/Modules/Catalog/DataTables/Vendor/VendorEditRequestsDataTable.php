<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Enums\EditableProductField;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Services\Requests\EditRequestService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Vendor › Products › Edit requests (VEN-013): the store's own requests (central table;
 * the shared repository reads without the tenant scope, so the store id is always passed).
 * Filters: search, status.
 *
 * @extends BaseDataTable<EditRequest>
 */
final class VendorEditRequestsDataTable extends BaseDataTable
{
    /**
     * @var array<string, mixed>
     */
    private array $filters = [];

    public function __construct(private readonly EditRequestService $requests) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function toJson(array $filters = []): JsonResponse
    {
        $this->filters = $filters;

        return parent::toJson($filters);
    }

    protected function query(): Builder
    {
        return $this->requests->listing([...$this->filters, 'tenant_id' => StoreCatalogService::tenantId()])->with('product.media');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $direction = static fn (string $order): string => $order === 'desc' ? 'desc' : 'asc';

        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('request', static fn (Builder $query, string $order) => $query->orderBy('catalog_edit_requests.id', $direction($order)))
            ->orderColumn('status', static fn (Builder $query, string $order) => $query->orderBy('catalog_edit_requests.status', $direction($order)))
            ->orderColumn('updated_at', static fn (Builder $query, string $order) => $query->orderBy('catalog_edit_requests.updated_at', $direction($order)))
            ->addColumn('request', static fn (EditRequest $request): string => view('catalog::vendor.edit-requests._request-cell', ['request' => $request])->render())
            ->addColumn('product', static fn (EditRequest $request): string => view('catalog::vendor.edit-requests._product-cell', ['request' => $request])->render())
            ->addColumn('fields', static fn (EditRequest $request): string => e(collect($request->changes)->map(static function (array $change): string {
                $field = EditableProductField::tryFrom((string) $change['field']);
                $label = $field?->label() ?? (string) $change['field'];

                return $change['locale'] ? $label.' ('.strtoupper((string) $change['locale']).')' : $label;
            })->unique()->implode(', ')))
            ->editColumn('status', static fn (EditRequest $request): string => view('catalog::vendor.edit-requests._status-cell', ['request' => $request])->render())
            ->editColumn('updated_at', static fn (EditRequest $request): string => e((string) $request->updated_at?->diffForHumans()))
            ->addColumn('actions', static fn (EditRequest $request): string => view('catalog::vendor.edit-requests._actions-cell', ['request' => $request])->render())
            ->rawColumns(['request', 'product', 'status', 'actions']);
    }
}
