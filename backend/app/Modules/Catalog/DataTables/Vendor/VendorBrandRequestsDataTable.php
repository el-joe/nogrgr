<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Repositories\Contracts\BrandRequestRepositoryContract;

/**
 * Vendor › Brand requests list.
 *
 * @extends VendorRequestsDataTable<BrandRequest>
 */
final class VendorBrandRequestsDataTable extends VendorRequestsDataTable
{
    public function __construct(private readonly BrandRequestRepositoryContract $requests) {}

    protected function kind(): RequestKind
    {
        return RequestKind::Brand;
    }

    protected function repository(): BrandRequestRepositoryContract
    {
        return $this->requests;
    }
}
