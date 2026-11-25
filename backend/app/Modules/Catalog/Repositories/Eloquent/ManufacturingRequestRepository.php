<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Repositories\Contracts\ManufacturingRequestRepositoryContract;

/**
 * @extends ThreadedRequestRepository<ManufacturingRequest>
 */
final class ManufacturingRequestRepository extends ThreadedRequestRepository implements ManufacturingRequestRepositoryContract
{
    protected function model(): string
    {
        return ManufacturingRequest::class;
    }

    protected function subjectColumn(): string
    {
        return 'product_name';
    }

    protected function statusValues(): array
    {
        return array_map('strval', ManufacturingRequestStatus::values());
    }

    protected function openStatusValues(): array
    {
        return ManufacturingRequestStatus::openValues();
    }
}
