<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Enums\ProductRequestStatus;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Repositories\Contracts\ProductRequestRepositoryContract;

/**
 * @extends ThreadedRequestRepository<ProductRequest>
 */
final class ProductRequestRepository extends ThreadedRequestRepository implements ProductRequestRepositoryContract
{
    protected function model(): string
    {
        return ProductRequest::class;
    }

    protected function subjectColumn(): string
    {
        return 'title';
    }

    protected function statusValues(): array
    {
        return array_map('strval', ProductRequestStatus::values());
    }

    protected function openStatusValues(): array
    {
        return ProductRequestStatus::openValues();
    }

    protected function archivable(): bool
    {
        return true;
    }
}
