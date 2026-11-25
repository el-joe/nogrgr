<?php

namespace App\Modules\Integrations\Payments;

use App\Modules\Integrations\Contracts\PaymentGatewayDriver;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Hosted-checkout drivers by gateway code. Only gateways that are active, have the
 * purpose's config enabled AND have a driver here are offered at checkout; the other
 * launch gateways join by adding a driver to DRIVERS.
 */
class PaymentDriverRegistry
{
    /**
     * @var array<string, class-string<PaymentGatewayDriver>>
     */
    public const DRIVERS = [
        'stripe' => StripeCheckoutDriver::class,
        'paypal' => PaypalCheckoutDriver::class,
    ];

    public function __construct(
        private readonly Container $container,
        private readonly PaymentGatewayRepositoryContract $gateways,
    ) {}

    public function supports(PaymentGatewayCode $code): bool
    {
        return array_key_exists($code->value, self::DRIVERS);
    }

    public function driver(PaymentGatewayCode $code): PaymentGatewayDriver
    {
        if (! $this->supports($code)) {
            throw PaymentGatewayException::unsupported();
        }

        /** @var PaymentGatewayDriver $driver */
        $driver = $this->container->make(self::DRIVERS[$code->value]);

        return $driver;
    }

    /**
     * Gateways a payer may choose for a purpose, in admin sort order.
     *
     * @return Collection<int, PaymentGateway>
     */
    public function checkoutGateways(GatewayPurpose $purpose): Collection
    {
        return $this->gateways->usableFor($purpose)
            ->filter(fn (PaymentGateway $gateway): bool => $this->supports($gateway->code))
            ->values();
    }

    /**
     * The usable gateway for a code and purpose, or null (inactive, disabled, no driver).
     */
    public function checkoutGateway(PaymentGatewayCode $code, GatewayPurpose $purpose): ?PaymentGateway
    {
        return $this->checkoutGateways($purpose)->first(static fn (PaymentGateway $gateway): bool => $gateway->code === $code);
    }

    /**
     * The purpose config of a gateway (credentials, mode), whether or not it is enabled:
     * webhooks and return checks of a started checkout still need it.
     */
    public function config(PaymentGatewayCode $code, GatewayPurpose $purpose): PaymentGatewayConfig
    {
        $gateway = $this->gateways->findByCode($code);

        if ($gateway === null) {
            throw PaymentGatewayException::misconfigured();
        }

        return $this->gateways->configFor($gateway, $purpose);
    }
}
