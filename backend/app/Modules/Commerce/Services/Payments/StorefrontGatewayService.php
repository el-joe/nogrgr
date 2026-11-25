<?php

namespace App\Modules\Commerce\Services\Payments;

use App\Modules\Commerce\DTOs\Payments\GatewayOption;
use App\Modules\Commerce\Enums\GatewayConnection;
use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Commerce\Models\StorePaymentGateway;
use App\Modules\Commerce\Repositories\Contracts\StorePaymentGatewayRepositoryContract;
use App\Modules\Integrations\Contracts\PaymentGatewayDriver;
use App\Modules\Integrations\Enums\ConnectionStatus;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Exceptions\PaymentGatewayException;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Modules\Integrations\Payments\PaypalCheckoutDriver;
use App\Modules\Integrations\Payments\StripePaymentIntentDriver;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;

/**
 * Storefront payment methods, filtered exactly like the old PaymentManager::storefrontGateways
 * (spec 08 §1/§3):
 *   store rows active and not hidden → whose code is centrally active (central gateway
 *   active with its vendor-payments config enabled) → with a storefront driver → ready:
 *     own keys (use_own): connection "connected" + every required credential present;
 *     central fallback:   the central config connected + every required credential present;
 *   → sorted alphabetically by name, nothing pre-selected. COD is never listed (hidden at
 *   checkout, client 2026-11-25); it stays a server-side method for manual orders.
 * The effective config (own keys + mode, or the central config) is what the drivers get.
 */
class StorefrontGatewayService
{
    /**
     * @var array<string, class-string<PaymentGatewayDriver>>
     */
    public const DRIVERS = [
        'stripe' => StripePaymentIntentDriver::class,
        'paypal' => PaypalCheckoutDriver::class,
    ];

    /**
     * Inline card panel gateways (spec 08 §2).
     *
     * @var list<string>
     */
    public const INLINE = ['stripe'];

    /**
     * Driver payment methods (old driver meta()['payment_methods']).
     *
     * @var array<string, list<string>>
     */
    public const PAYMENT_METHODS = [
        'stripe' => ['card', 'apple_pay', 'google_pay', 'wallets'],
        'paypal' => ['paypal_balance', 'card'],
    ];

    public const PURPOSE = GatewayPurpose::VendorPayments;

    public function __construct(
        private readonly StorePaymentGatewayRepositoryContract $storeGateways,
        private readonly PaymentGatewayRepositoryContract $centralGateways,
        private readonly Container $container,
    ) {}

    /**
     * @return list<GatewayOption>
     */
    public function options(): array
    {
        $central = $this->centrallyActive();
        $options = [];

        foreach ($this->storeGateways->offered() as $row) {
            $gateway = $central->get($row->code->value);

            if (! $gateway instanceof PaymentGateway || ! $this->supports($row->code)) {
                continue;
            }

            $option = $this->option($row, $gateway);

            if ($option !== null) {
                $options[] = $option;
            }
        }

        usort($options, static fn (GatewayOption $a, GatewayOption $b): int => strcasecmp($a->name, $b->name));

        return $options;
    }

    public function option(StorePaymentGateway $row, PaymentGateway $central): ?GatewayOption
    {
        $config = $row->use_own ? $this->ownConfig($row) : $this->centralGateways->configFor($central, self::PURPOSE);
        $connected = $row->use_own ? $row->connection_status === GatewayConnection::Connected : $config->status() === ConnectionStatus::Connected;

        if (! $connected || ! $this->hasRequiredKeys($row->code, $config)) {
            return null;
        }

        return new GatewayOption(
            source: $row->use_own ? PaymentSource::Tenant : PaymentSource::Central,
            id: $row->use_own ? $row->id : $central->id,
            code: $row->code,
            name: $row->code->brand(),
            mode: $config->mode,
            logoUrl: null,
            paymentMethods: self::PAYMENT_METHODS[$row->code->value] ?? [],
            inline: in_array($row->code->value, self::INLINE, true),
            config: $config,
        );
    }

    /**
     * The listed option of a code (place order / start payment "still listed" guard).
     */
    public function find(PaymentGatewayCode $code): ?GatewayOption
    {
        foreach ($this->options() as $option) {
            if ($option->code === $code) {
                return $option;
            }
        }

        return null;
    }

    /**
     * Config for webhooks and return checks of a started payment, whether or not the
     * gateway is still listed: the store's own keys when it uses them, else the central config.
     */
    public function configFor(PaymentGatewayCode $code): ?PaymentGatewayConfig
    {
        $row = $this->storeGateways->findByCode($code);

        if ($row !== null && $row->use_own) {
            return $this->ownConfig($row);
        }

        $central = $this->centralGateways->findByCode($code);

        return $central !== null ? $this->centralGateways->configFor($central, self::PURPOSE) : null;
    }

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
     * Central gateways usable for storefront orders, keyed by code.
     *
     * @return Collection<string, PaymentGateway>
     */
    private function centrallyActive(): Collection
    {
        return $this->centralGateways->usableFor(self::PURPOSE)
            ->keyBy(static fn (PaymentGateway $gateway): string => $gateway->code->value)
            ->toBase();
    }

    private function ownConfig(StorePaymentGateway $row): PaymentGatewayConfig
    {
        return new PaymentGatewayConfig([
            'purpose' => self::PURPOSE,
            'is_enabled' => $row->is_active,
            'mode' => $row->mode,
            'credentials' => $row->credentials ?? [],
        ]);
    }

    private function hasRequiredKeys(PaymentGatewayCode $code, PaymentGatewayConfig $config): bool
    {
        foreach ($code->credentialFields() as $field) {
            if ($field->required && $config->credential($field->key) === null) {
                return false;
            }
        }

        return true;
    }
}
