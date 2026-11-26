<?php

namespace App\Modules\Reporting\Http\Controllers\Admin;

use App\Modules\Platform\Models\Tenant;
use App\Modules\Reporting\DataTables\VendorOrdersDataTable;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Orders table of the vendor profile "Stats & orders" tab (ADM-041e).
 */
class VendorStatsController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, VendorOrdersDataTable $table): JsonResponse
    {
        return $table->forTenant((int) $tenant->id)->toJsonFromRequest($request, VendorOrdersDataTable::FILTERS);
    }
}
