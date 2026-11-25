<?php

namespace App\Modules\Billing\Repositories\Contracts;

use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Models\PaymentLog;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends RepositoryContract<PaymentLog>
 */
interface PaymentLogRepositoryContract extends RepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters  status, gateway, search
     * @return Builder<PaymentLog>
     */
    public function listing(array $filters = []): Builder;

    public function findForDetail(int $id): PaymentLog;

    /**
     * The log of a gateway checkout (gateway_reference = Stripe session / PayPal order id).
     */
    public function findByGatewayReference(PaymentGateway $gateway, string $reference): ?PaymentLog;

    /**
     * Re-read a log with a row lock (inside a transaction) so a return-URL check and a
     * webhook never settle the same payment twice.
     */
    public function lockForUpdate(PaymentLog $log): PaymentLog;
}
