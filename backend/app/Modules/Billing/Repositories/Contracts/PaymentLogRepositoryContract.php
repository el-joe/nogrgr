<?php

namespace App\Modules\Billing\Repositories\Contracts;

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
}
