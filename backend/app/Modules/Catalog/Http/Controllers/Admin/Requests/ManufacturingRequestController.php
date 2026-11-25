<?php

namespace App\Modules\Catalog\Http\Controllers\Admin\Requests;

use App\Modules\Catalog\DataTables\ManufacturingRequestsDataTable;
use App\Modules\Catalog\Enums\RequestKind;

/**
 * Requests center › Manufacturing (ADM-018e/f): chat, internal notes, status pipeline
 * and payment requests (Billing invoices).
 */
class ManufacturingRequestController extends ThreadedRequestController
{
    protected function kind(): RequestKind
    {
        return RequestKind::Manufacturing;
    }

    protected function table(): ManufacturingRequestsDataTable
    {
        return app(ManufacturingRequestsDataTable::class);
    }
}
