<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Repositories\Contracts\ManufacturingRequestRepositoryContract;

/**
 * Vendor › Manufacturing requests list.
 *
 * @extends VendorRequestsDataTable<ManufacturingRequest>
 */
final class VendorManufacturingRequestsDataTable extends VendorRequestsDataTable
{
    public function __construct(private readonly ManufacturingRequestRepositoryContract $requests) {}

    protected function kind(): RequestKind
    {
        return RequestKind::Manufacturing;
    }

    protected function repository(): ManufacturingRequestRepositoryContract
    {
        return $this->requests;
    }
}
