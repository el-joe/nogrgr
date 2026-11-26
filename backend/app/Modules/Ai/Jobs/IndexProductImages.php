<?php

namespace App\Modules\Ai\Jobs;

use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\ImageSearch\ImageIndexService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * (Re)indexes one product's images for image search. `central` = a central product
 * (central index); otherwise a store-own product, dispatched inside the store's tenant
 * context (queue tenancy restores it). Idempotent: unchanged images are skipped.
 */
class IndexProductImages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly bool $central,
        public readonly int $productId,
        public readonly bool $force = false,
    ) {}

    public function uniqueId(): string
    {
        return ($this->central ? 'central:' : 'tenant:'.(tenant()?->getTenantKey() ?? '0').':').$this->productId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(ImageIndexService $index): void
    {
        try {
            $this->central ? $index->syncCentral($this->productId, $this->force) : $index->syncOwn($this->productId, $this->force);
        } catch (AiRequestException $exception) {
            if (! $exception->type->isFatal()) {
                throw $exception;
            }
            // Not configured / bad key / provider quota: retrying cannot help; the backfill command re-queues later.
        }
    }
}
