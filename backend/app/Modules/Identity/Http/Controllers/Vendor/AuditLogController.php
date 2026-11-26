<?php

namespace App\Modules\Identity\Http\Controllers\Vendor;

use App\Modules\Identity\DataTables\VendorAuditEventsDataTable;
use App\Modules\Identity\Services\VendorAuditLogService;
use App\Shared\Http\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Audit log (VEN-086, Figma Vendor Panel 91:118575): who on the store
 * team (or Nogrgr support) did what and when, with search + member / area / date filters.
 * Read-only; permission settings.audit.view.
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly VendorAuditLogService $audit) {}

    public function index(): View
    {
        return view('identity::vendor.audit.index', [
            'members' => $this->audit->memberOptions(),
            'areas' => $this->audit->areaOptions(),
            'timezone' => $this->audit->timezone(),
        ]);
    }

    public function data(Request $request, VendorAuditEventsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, VendorAuditEventsDataTable::FILTERS);
    }
}
