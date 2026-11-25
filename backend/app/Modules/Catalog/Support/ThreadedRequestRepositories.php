<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Requests\BrandRequest;
use App\Modules\Catalog\Models\Requests\ManufacturingRequest;
use App\Modules\Catalog\Models\Requests\ProductRequest;
use App\Modules\Catalog\Repositories\Contracts\BrandRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ManufacturingRequestRepositoryContract;
use App\Modules\Catalog\Repositories\Contracts\ProductRequestRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Routes kind-agnostic request operations (find, create, update, unread count) to the
 * repository of the request type, so the thread / status services stay generic.
 */
final class ThreadedRequestRepositories
{
    public function __construct(
        private readonly ProductRequestRepositoryContract $products,
        private readonly ManufacturingRequestRepositoryContract $manufacturing,
        private readonly BrandRequestRepositoryContract $brands,
    ) {}

    public function find(RequestKind $kind, int $id): (Model&ThreadedRequest)|null
    {
        return match ($kind) {
            RequestKind::Product => $this->products->find($id),
            RequestKind::Manufacturing => $this->manufacturing->find($id),
            RequestKind::Brand => $this->brands->find($id),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(RequestKind $kind, array $attributes): Model&ThreadedRequest
    {
        return match ($kind) {
            RequestKind::Product => $this->products->create($attributes),
            RequestKind::Manufacturing => $this->manufacturing->create($attributes),
            RequestKind::Brand => $this->brands->create($attributes),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Model&ThreadedRequest $request, array $attributes): void
    {
        match (true) {
            $request instanceof ProductRequest => $this->products->update($request, $attributes),
            $request instanceof ManufacturingRequest => $this->manufacturing->update($request, $attributes),
            $request instanceof BrandRequest => $this->brands->update($request, $attributes),
            default => throw new InvalidArgumentException('Unknown request model '.$request::class),
        };
    }

    public function adminUnreadCount(RequestKind $kind): int
    {
        return match ($kind) {
            RequestKind::Product => $this->products->adminUnreadCount(),
            RequestKind::Manufacturing => $this->manufacturing->adminUnreadCount(),
            RequestKind::Brand => $this->brands->adminUnreadCount(),
        };
    }
}
