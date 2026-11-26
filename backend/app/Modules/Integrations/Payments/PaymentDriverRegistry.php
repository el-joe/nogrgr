<?php

namespace App\Modules\Integrations\Payments;

use App\Modules\Integrations\Contracts\PaymentGatewayDriver;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Checkout drivers by gateway code. Only gateways that are active, have the purpose's
 * config enabled AND have a driver here are offered at checkout; the other launch
 * gateways join by adding a driver to DRIVERS.
 *
 * Inline card (decision #78 b): a payment page that renders the shared Stripe card
 * (Stripe.js Card Element) starts its payment inside `inline()`; while that runs, Stripe
 * uses the PaymentIntent driver (client secret, no redirect) instead of hosted Checkout.
 * Bound scoped, so the mode never leaks into another request or job.
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

    /**
     * Inline card drivers (PaymentIntents confirmed by Stripe.js on our page).
     *
     * @var array<string, class-string<PaymentGatewayDriver>>
     */
    public const INLINE_DRIVERS = [
        'stripe' => StripePaymentIntentDriver::class,
    ];

    private bool $inline = false;

    public function __construct(
        private readonly Container $container,
        private readonly PaymentGatewayRepositoryContract $gateways,
    ) {}

    public function supports(PaymentGatewayCode $code): bool
    {
        return array_key_exists($code->value, self::DRIVERS);
    }

    /**
     * The driver of a checkout: the inline one when asked for and the gateway has one.
     */
    public function driver(PaymentGatewayCode $code, bool $inline = false): PaymentGatewayDriver
    {
        if (! $this->supports($code)) {
            throw PaymentGatewayException::unsupported();
        }

        $class = $inline && $this->hasInline($code) ? self::INLINE_DRIVERS[$code->value] : self::DRIVERS[$code->value];

        /** @var PaymentGatewayDriver $driver */
        $driver = $this->container->make($class);

        return $driver;
    }

    public function hasInline(PaymentGatewayCode $code): bool
    {
        return array_key_exists($code->value, self::INLINE_DRIVERS);
    }

    /**
     * Whether a checkout started now for this gateway is an inline card payment.
     */
    public function startsInline(PaymentGatewayCode $code): bool
    {
        return $this->inline && $this->hasInline($code);
    }

    /**
     * Run a payment start in inline card mode (the page renders the Stripe card).
     *
     * @template T
     *
     * @param  Closure(): T  $start
     * @return T
     */
    public function inline(Closure $start): mixed
    {
        $previous = $this->inline;
        $this->inline = true;

        try {
            return $start();
        } finally {
            $this->inline = $previous;
        }
    }

    /**
     * The publishable key the page's Stripe card mounts with, when the gateway is usable
     * for the purpose and has an inline driver; null otherwise.
     */
    public function publishableKey(PaymentGatewayCode $code, GatewayPurpose $purpose): ?string
    {
        if (! $this->hasInline($code) || $this->checkoutGateway($code, $purpose) === null) {
            return null;
        }

        return $this->config($code, $purpose)->credential('publishable_key');
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
