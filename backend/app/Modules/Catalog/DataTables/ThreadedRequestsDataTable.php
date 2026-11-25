<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Repositories\Contracts\ThreadedRequestRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Yajra\DataTables\EloquentDataTable;

/**
 * Requests center lists with a conversation: product (ADM-018c), manufacturing (ADM-018e)
 * and brand (ADM-018g) requests. Shared columns here, kind-specific ones in subclasses.
 *
 * @template TModel of Model&ThreadedRequest
 *
 * @extends BaseDataTable<TModel>
 */
abstract class ThreadedRequestsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'priority', 'tenant_id', 'archived', 'search'];

    abstract public function kind(): RequestKind;

    /**
     * @return ThreadedRequestRepositoryContract<TModel>
     */
    abstract protected function repository(): ThreadedRequestRepositoryContract;

    /**
     * Extra kind-specific columns.
     *
     * @return list<string>
     */
    protected function extraColumns(EloquentDataTable $table, string $prefix): array
    {
        return [];
    }

    protected function query(): Builder
    {
        return $this->repository()->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->repository()->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        $kind = $this->kind();
        $prefix = $this->repository()->query()->getModel()->getTable();

        $table
            ->addColumn('request', fn (Model&ThreadedRequest $request): string => view('catalog::admin.requests.partials._request-cell', ['request' => $request])->render())
            ->addColumn('vendor', fn (Model $request): string => view('catalog::admin.requests.partials._vendor-cell', ['name' => $request->getAttribute('store_name'), 'slug' => $request->getAttribute('store_slug')])->render())
            ->editColumn('status', fn (Model&ThreadedRequest $request): string => view('catalog::admin.requests.partials._badge', ['tone' => $request->statusTone(), 'label' => $request->statusLabel()])->render())
            ->addColumn('unread', fn (Model $request): string => view('catalog::admin.requests.partials._unread-cell', ['count' => (int) $request->getAttribute('admin_unread_count'), 'seen' => $request->getAttribute('admin_seen_at') !== null])->render())
            ->addColumn('updated', fn (Model $request): string => view('catalog::admin.requests.partials._date-cell', ['date' => $request->getAttribute('last_message_at') ?? $request->getAttribute('updated_at')])->render())
            ->addColumn('actions', fn (Model&ThreadedRequest $request): string => view('catalog::admin.requests.threads._actions', ['request' => $request, 'kind' => $kind])->render())
            ->orderColumn('request', $prefix.'.id $1')
            ->orderColumn('vendor', 'tenants.name $1')
            ->orderColumn('status', $prefix.'.status $1')
            ->orderColumn('unread', 'admin_unread_count $1')
            ->orderColumn('updated', 'COALESCE('.$prefix.'.last_message_at, '.$prefix.'.updated_at) $1');

        $columns = ['id', 'request', 'vendor', 'status', 'unread', 'updated', 'actions', ...$this->extraColumns($table, $prefix)];

        return $table->rawColumns(['request', 'vendor', 'status', 'unread', 'updated', 'actions', 'priority'])->only($columns);
    }
}
