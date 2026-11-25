<?php

namespace App\Modules\Catalog\Http\Controllers\Admin\Requests;

use App\Modules\Catalog\DataTables\BrandRequestsDataTable;
use App\Modules\Catalog\Enums\RequestKind;

/**
 * Requests center › Brand requests (ADM-018g/h): chat, notes, status and payment requests.
 */
class BrandRequestController extends ThreadedRequestController
{
    protected function kind(): RequestKind
    {
        return RequestKind::Brand;
    }

    protected function table(): BrandRequestsDataTable
    {
        return app(BrandRequestsDataTable::class);
    }
}
