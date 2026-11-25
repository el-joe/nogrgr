<?php

namespace App\Modules\Catalog\DataTables\Vendor;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Repositories\Contracts\ProductRequestRepositoryContract;

/**
 * Vendor › Product requests list.
 *
 * @extends VendorRequestsDataTable<ProductRequest>
 */
final class VendorProductRequestsDataTable extends VendorRequestsDataTable
{
    public function __construct(private readonly ProductRequestRepositoryContract $requests) {}

    protected function kind(): RequestKind
    {
        return RequestKind::Product;
    }

    protected function repository(): ProductRequestRepositoryContract
    {
        return $this->requests;
    }
}
