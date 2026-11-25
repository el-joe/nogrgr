<?php

namespace App\Modules\Commerce\Repositories\Eloquent;

use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Commerce\Repositories\Contracts\StorePaymentGatewayRepositoryContract;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<StorePaymentGateway>
 */
class StorePaymentGatewayRepository extends BaseRepository implements StorePaymentGatewayRepositoryContract
{
    protected function model(): string
    {
        return StorePaymentGateway::class;
    }

    public function offered(): Collection
    {
        return $this->query()->where('is_active', true)->where('is_hidden', false)->get();
    }

    public function findByCode(PaymentGatewayCode $code): ?StorePaymentGateway
    {
        return $this->query()->where('code', $code->value)->first();
    }
}
