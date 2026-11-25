<?php

namespace App\Modules\Catalog\DataTables;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Repositories\Contracts\BrandRequestRepositoryContract;

/**
 * Requests center › Brand requests (ADM-018g).
 *
 * @extends ThreadedRequestsDataTable<BrandRequest>
 */
final class BrandRequestsDataTable extends ThreadedRequestsDataTable
{
    public function __construct(private readonly BrandRequestRepositoryContract $requests) {}

    public function kind(): RequestKind
    {
        return RequestKind::Brand;
    }

    protected function repository(): BrandRequestRepositoryContract
    {
        return $this->requests;
    }
}
