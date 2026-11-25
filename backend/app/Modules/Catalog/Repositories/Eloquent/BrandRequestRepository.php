<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\BrandRequestStatus;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Repositories\Contracts\BrandRequestRepositoryContract;

/**
 * @extends ThreadedRequestRepository<BrandRequest>
 */
final class BrandRequestRepository extends ThreadedRequestRepository implements BrandRequestRepositoryContract
{
    protected function model(): string
    {
        return BrandRequest::class;
    }

    protected function subjectColumn(): string
    {
        return 'brand_name';
    }

    protected function statusValues(): array
    {
        return array_map('strval', BrandRequestStatus::values());
    }

    protected function openStatusValues(): array
    {
        return BrandRequestStatus::openValues();
    }
}
