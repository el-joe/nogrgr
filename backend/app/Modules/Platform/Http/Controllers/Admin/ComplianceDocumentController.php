<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\Http\Requests\Admin\ComplianceDocumentStatusRequest;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Services\ComplianceService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * KYC document files and per-document flags. Files are only ever streamed from their
 * private disk through ComplianceService (inline preview, or ?download=1); a document
 * of another profile or a missing file answers 404.
 */
class ComplianceDocumentController extends Controller
{
    public function __construct(private readonly ComplianceService $compliance) {}

    public function show(Request $request, ComplianceProfile $complianceProfile, int $document): StreamedResponse
    {
        $file = $this->compliance->document($complianceProfile, $document);

        abort_unless($this->compliance->hasFile($file), 404);

        return $this->compliance->stream($complianceProfile, $file->id, $request->boolean('download'));
    }

    public function status(ComplianceDocumentStatusRequest $request, ComplianceProfile $complianceProfile, int $document): JsonResponse
    {
        $file = $this->compliance->document($complianceProfile, $document);
        $status = $request->documentStatus();
        $this->compliance->setDocumentStatus($complianceProfile, $file->id, $status);

        return JsonResponder::success(__('platform::compliance.document.flagged', ['document' => $file->type->label(), 'status' => $status->label()]));
    }
}
