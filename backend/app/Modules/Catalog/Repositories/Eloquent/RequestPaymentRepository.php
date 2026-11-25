<?php

namespace App\Modules\Catalog\Repositories\Eloquent;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Models\Requests\RequestPayment;
use App\Modules\Catalog\Repositories\Contracts\RequestPaymentRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<RequestPayment>
 */
final class RequestPaymentRepository extends BaseRepository implements RequestPaymentRepositoryContract
{
    protected function model(): string
    {
        return RequestPayment::class;
    }

    public function query(): Builder
    {
        return RequestPayment::withoutTenantScope();
    }

    public function createFor(Model&ThreadedRequest $request, array $attributes): RequestPayment
    {
        /** @var RequestPayment $payment */
        $payment = $this->query()->create([
            ...$attributes,
            'requestable_type' => $request->getMorphClass(),
            'requestable_id' => $request->getKey(),
            'tenant_id' => $request->tenantId(),
        ]);

        return $payment;
    }

    public function forRequest(Model&ThreadedRequest $request): Collection
    {
        return $this->query()
            ->with('invoice')
            ->where('requestable_type', $request->getMorphClass())
            ->where('requestable_id', $request->getKey())
            ->orderByDesc('id')
            ->get();
    }

    public function findByInvoice(int $invoiceId): ?RequestPayment
    {
        return $this->query()->where('invoice_id', $invoiceId)->first();
    }
}
