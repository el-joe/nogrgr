<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Localization\Services\CountryService;
use App\Modules\Platform\DataTables\ComplianceDataTable;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Http\Requests\Admin\ComplianceDecisionRequest;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Services\ComplianceService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Vendors › Compliance / KYC (ADM-044 queue, ADM-044a review): status tabs with
 * counts, server-side table, the store review page and the decision (verified / needs
 * action / rejected), which e-mails the store owner through ComplianceReviewed.
 * Permissions: vendors.kyc.view / vendors.kyc.manage.
 */
class ComplianceController extends Controller
{
    public function __construct(
        private readonly ComplianceService $compliance,
        private readonly CountryService $countries,
    ) {}

    public function index(Request $request): View
    {
        $status = ComplianceStatus::tryFrom((string) $request->query('status'));
        $status = in_array($status, ComplianceStatus::reviewable(), true) ? $status : ComplianceStatus::NeedsReview;

        return view('platform::admin.compliance.index', [
            'status' => $status,
            'counts' => $this->compliance->counts(),
            'documentTypes' => ComplianceDocumentType::options(),
            'countries' => $this->countries->options(),
        ]);
    }

    public function data(Request $request, ComplianceDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, ComplianceDataTable::FILTERS);
    }

    public function show(ComplianceProfile $complianceProfile): View
    {
        $complianceProfile->loadMissing(['documents', 'reviewer', 'tenant']);

        return view('platform::admin.compliance.show', [
            'profile' => $complianceProfile,
            'files' => $this->compliance->fileAvailability($complianceProfile),
        ]);
    }

    public function decide(ComplianceDecisionRequest $request, ComplianceProfile $complianceProfile): JsonResponse
    {
        $data = $request->toData();
        $this->compliance->decide($complianceProfile, $data);

        return JsonResponder::success(__('platform::compliance.decision.saved_'.$data->status->value));
    }
}
