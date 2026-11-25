<?php

namespace App\Modules\Catalog\Repositories\Contracts;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Models\Requests\RequestPayment;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Payment requests of manufacturing / brand requests (central DB).
 *
 * @extends RepositoryContract<RequestPayment>
 */
interface RequestPaymentRepositoryContract extends RepositoryContract
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFor(Model&ThreadedRequest $request, array $attributes): RequestPayment;

    /**
     * @return Collection<int, RequestPayment>
     */
    public function forRequest(Model&ThreadedRequest $request): Collection;

    public function findByInvoice(int $invoiceId): ?RequestPayment;
}
