<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Repositories\Contracts\VendorRegistrationRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Pending registrations (ADM-042). Only the rendered columns leave the server:
 * the row holds the password hash, token hash and meta, which must never reach the browser.
 *
 * @extends BaseDataTable<VendorRegistration>
 */
final class PendingRegistrationsDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'plan_id', 'search'];

    public function __construct(private readonly VendorRegistrationRepositoryContract $registrations) {}

    protected function query(): Builder
    {
        return $this->registrations->pending();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->registrations->pending($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('applicant', fn (VendorRegistration $registration): string => view('platform::admin.registrations._applicant-cell', ['registration' => $registration])->render())
            ->addColumn('store', fn (VendorRegistration $registration): string => view('platform::admin.registrations._store-cell', ['registration' => $registration])->render())
            ->addColumn('plan', fn (VendorRegistration $registration): string => view('platform::admin.registrations._plan-cell', ['registration' => $registration])->render())
            ->addColumn('step', fn (VendorRegistration $registration): string => e($registration->status->step()->label()))
            ->addColumn('started', fn (VendorRegistration $registration): string => view('platform::admin.registrations._date-cell', ['date' => $registration->created_at])->render())
            ->editColumn('status', fn (VendorRegistration $registration): string => view('platform::admin.registrations._status-cell', ['registration' => $registration])->render())
            ->addColumn('actions', fn (VendorRegistration $registration): string => view('platform::admin.registrations._actions', ['registration' => $registration])->render())
            ->orderColumn('applicant', 'vendor_registrations.email $1')
            ->orderColumn('store', 'vendor_registrations.store_name $1')
            ->orderColumn('plan', 'vendor_registrations.plan_id $1')
            ->orderColumn('step', 'vendor_registrations.status $1')
            ->orderColumn('started', 'vendor_registrations.id $1')
            ->orderColumn('status', 'vendor_registrations.status $1')
            ->rawColumns(['applicant', 'store', 'plan', 'step', 'started', 'status', 'actions'])
            ->only(['id', 'applicant', 'store', 'plan', 'step', 'started', 'status', 'actions']);
    }
}
