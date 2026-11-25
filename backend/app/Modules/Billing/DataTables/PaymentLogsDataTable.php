<?php

namespace App\Modules\Billing\DataTables;

use App\Modules\Billing\Models\PaymentLog;
use App\Modules\Billing\Repositories\Contracts\PaymentLogRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin payment logs list (ADM-055).
 *
 * @extends BaseDataTable<PaymentLog>
 */
final class PaymentLogsDataTable extends BaseDataTable
{
    public function __construct(private readonly PaymentLogRepositoryContract $logs) {}

    protected function query(): Builder
    {
        return $this->logs->listing();
    }

    /**
     * The repository listing applies every filter (status, search…).
     */
    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->logs->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('id', fn (PaymentLog $log): string => view('billing::admin.payment-logs._reference-cell', ['log' => $log])->render())
            ->editColumn('gateway', fn (PaymentLog $log): string => e($log->gateway->label()))
            ->editColumn('purpose', fn (PaymentLog $log): string => e($log->purpose->label().($log->tenant ? ' · '.$log->tenant->getAttribute('name') : '')))
            ->editColumn('amount_minor', fn (PaymentLog $log): string => '<bdi dir="ltr">'.e($log->amount()->format()).'</bdi>')
            ->editColumn('status', fn (PaymentLog $log): string => view('billing::admin._status', ['status' => $log->status])->render())
            ->addColumn('actions', fn (PaymentLog $log): string => view('billing::admin.payment-logs._actions', ['log' => $log])->render())
            ->rawColumns(['id', 'status', 'actions']);
    }
}
