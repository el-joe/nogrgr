<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Platform\Enums\ComplianceAccountType;
use App\Modules\Platform\Enums\ComplianceSection;
use App\Modules\Platform\Http\Requests\Vendor\ComplianceCenterRequest;
use App\Modules\Platform\Http\Requests\Vendor\ComplianceCitiesRequest;
use App\Modules\Platform\Services\ComplianceCenterService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor › Settings › Compliance center (VEN-084, Figma Vendor Panel 91:117308): stepper
 * identity → company → bank → documents → terms with the Status card, save as draft,
 * submit / re-submit for review, private document download / removal and the city
 * options of a country. Writes the store's central KYC profile (the one the admin reviews
 * in ADM-044), always pinned to the current tenant.
 * Permissions: settings.compliance.view (page, files, cities) / settings.compliance.manage.
 */
class ComplianceController extends Controller
{
    public function __construct(private readonly ComplianceCenterService $center) {}

    public function edit(Request $request): View
    {
        $profile = $this->center->profile($this->tenantId());
        $step = ComplianceSection::tryFrom((string) $request->query('step'));
        $sections = $this->center->sections($profile);

        if ($step === null) {
            $firstOpen = collect($sections)->search(fn ($state): bool => $state->needsAttention());
            $step = ComplianceSection::tryFrom(is_string($firstOpen) ? $firstOpen : '') ?? ComplianceSection::Identity;
        }

        return view('platform::vendor.compliance.edit', [
            'profile' => $profile,
            'sections' => $sections,
            'missing' => $this->center->missing($profile),
            'step' => $step,
            'files' => $this->center->fileAvailability($profile),
            'countries' => $this->center->countryOptions(),
            'accountTypes' => ComplianceAccountType::options(),
            'termsVersion' => $this->center->termsVersion(),
            'termsUrl' => $this->center->termsUrl(),
            'termsAccepted' => $this->center->termsAccepted($profile),
        ]);
    }

    public function update(ComplianceCenterRequest $request): JsonResponse
    {
        $this->center->save($this->tenantId(), $request->toData(), $request->ip());

        return JsonResponder::success(__('platform::vendor_compliance.saved'), redirect: $this->editUrl($request->step()));
    }

    public function submit(ComplianceCenterRequest $request): JsonResponse
    {
        $profile = $this->center->submit($this->tenantId(), $request->toData(), $request->ip());

        return JsonResponder::success(__('platform::vendor_compliance.submitted'), ['status' => $profile->status->value], $this->editUrl(null));
    }

    public function cities(ComplianceCitiesRequest $request, int $country): JsonResponse
    {
        return response()->json(['results' => $this->center->cityOptions($country, $request->term()), 'pagination' => ['more' => false]]);
    }

    public function document(Request $request, int $document): StreamedResponse
    {
        return $this->center->stream($this->tenantId(), $document, $request->boolean('download'));
    }

    public function destroyDocument(Request $request, int $document): JsonResponse
    {
        $type = $this->center->removeDocument($this->tenantId(), $document);
        $step = ComplianceSection::forDocument($type);

        return JsonResponder::success(__('platform::vendor_compliance.document_removed', ['document' => $type->label()]), redirect: $this->editUrl($step->value));
    }

    private function editUrl(?string $step): string
    {
        return vendor_route('settings.compliance.edit', $step !== null ? ['step' => $step] : []);
    }

    private function tenantId(): int
    {
        return (int) tenant()?->getTenantKey();
    }
}
