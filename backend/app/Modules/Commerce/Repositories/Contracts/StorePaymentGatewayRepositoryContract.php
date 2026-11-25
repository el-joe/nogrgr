<?php

namespace App\Modules\Commerce\Repositories\Contracts;

use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<StorePaymentGateway>
 */
interface StorePaymentGatewayRepositoryContract extends RepositoryContract
{
    /**
     * Active, not hidden store rows.
     *
     * @return Collection<int, StorePaymentGateway>
     */
    public function offered(): Collection;

    public function findByCode(PaymentGatewayCode $code): ?StorePaymentGateway;
}
