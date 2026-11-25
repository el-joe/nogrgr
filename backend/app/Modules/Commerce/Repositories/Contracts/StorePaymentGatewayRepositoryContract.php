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

    /**
     * @return Collection<int, StorePaymentGateway>
     */
    public function allRows(): Collection;

    public function firstOrNewByCode(PaymentGatewayCode $code): StorePaymentGateway;

    /**
     * Makes one row the primary gateway (and no other).
     */
    public function markPrimary(StorePaymentGateway $row): void;
}
