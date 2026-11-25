<?php

namespace App\Modules\Integrations\Repositories\Contracts;

use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryContract<PaymentGateway>
 */
interface PaymentGatewayRepositoryContract extends RepositoryContract
{
    /**
     * Every gateway with its configs, ordered by sort order then code.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function listed(): Collection;

    public function findByCode(PaymentGatewayCode $code): ?PaymentGateway;

    /**
     * Insert the gateways of the catalogue that have no row yet (inactive).
     *
     * @param  list<PaymentGatewayCode>  $codes
     */
    public function ensure(array $codes): void;

    public function configFor(PaymentGateway $gateway, GatewayPurpose $purpose): PaymentGatewayConfig;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveConfig(PaymentGatewayConfig $config, array $attributes): PaymentGatewayConfig;

    /**
     * Active gateways whose config of the purpose is enabled (for checkouts).
     *
     * @return Collection<int, PaymentGateway>
     */
    public function usableFor(GatewayPurpose $purpose): Collection;
}
