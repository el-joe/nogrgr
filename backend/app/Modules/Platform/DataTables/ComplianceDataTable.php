<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Models\ComplianceDocument;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Repositories\Contracts\ComplianceProfileRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Compliance / KYC queue (ADM-044). Only the rendered columns leave the server
 * (the profile holds the encrypted IBAN and the decision note).
 *
 * @extends BaseDataTable<ComplianceProfile>
 */
final class ComplianceDataTable extends BaseDataTable
{
    public const FILTERS = ['status', 'document', 'country_id', 'search'];

    public function __construct(private readonly ComplianceProfileRepositoryContract $profiles) {}

    protected function query(): Builder
    {
        return $this->profiles->listing();
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        return $this->profiles->listing($filters);
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->addColumn('store', fn (ComplianceProfile $profile): string => view('platform::admin.compliance._store-cell', ['profile' => $profile])->render())
            ->addColumn('documents', fn (ComplianceProfile $profile): string => $this->documents($profile))
            ->addColumn('submitted', fn (ComplianceProfile $profile): string => view('platform::admin.registrations._date-cell', ['date' => $profile->submitted_at, 'empty' => __('platform::compliance.not_submitted')])->render())
            ->editColumn('risk', fn (ComplianceProfile $profile): string => view('platform::admin.compliance._badge-cell', ['tone' => $profile->risk->tone(), 'label' => $profile->risk->label()])->render())
            ->editColumn('status', fn (ComplianceProfile $profile): string => view('platform::admin.compliance._badge-cell', ['tone' => $profile->status->tone(), 'label' => $profile->status->label()])->render())
            ->addColumn('actions', fn (ComplianceProfile $profile): string => view('platform::admin.compliance._actions', ['profile' => $profile])->render())
            ->orderColumn('store', 'tenants.name $1')
            ->orderColumn('submitted', 'compliance_profiles.submitted_at $1')
            ->orderColumn('risk', "CASE compliance_profiles.risk WHEN 'low' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END $1")
            ->orderColumn('status', 'compliance_profiles.status $1')
            ->rawColumns(['store', 'documents', 'submitted', 'risk', 'status', 'actions'])
            ->only(['id', 'store', 'documents', 'submitted', 'risk', 'status', 'actions']);
    }

    private function documents(ComplianceProfile $profile): string
    {
        $labels = $profile->documents
            ->map(fn (ComplianceDocument $document): ComplianceDocumentType => $document->type)
            ->unique()
            ->map(fn (ComplianceDocumentType $type): string => $type->shortLabel())
            ->implode(', ');

        return $labels !== ''
            ? '<span class="text-ink">'.e($labels).'</span>'
            : '<span class="text-ink-secondary">'.e(__('platform::compliance.no_documents_short')).'</span>';
    }
}
