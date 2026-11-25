<?php

namespace App\Modules\Commerce\DTOs\Payments;

use App\Modules\Commerce\Enums\PaymentSource;
use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGatewayConfig;

/**
 * One payment method offered at checkout (spec 08 §1 item: source, id, code, name, mode,
 * logo_url, payment_methods) plus the resolved config (never serialised).
 */
final readonly class GatewayOption
{
    /**
     * @param  list<string>  $paymentMethods
     */
    public function __construct(
        public PaymentSource $source,
        public int $id,
        public PaymentGatewayCode $code,
        public string $name,
        public GatewayMode $mode,
        public ?string $logoUrl,
        public array $paymentMethods,
        public bool $inline,
        public PaymentGatewayConfig $config,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source->value,
            'id' => $this->id,
            'code' => $this->code->value,
            'name' => $this->name,
            'mode' => $this->mode->value,
            'logo_url' => $this->logoUrl,
            'payment_methods' => $this->paymentMethods,
            'inline' => $this->inline,
            'publishable_key' => $this->inline ? $this->config->credential('publishable_key') : null,
        ];
    }
}
