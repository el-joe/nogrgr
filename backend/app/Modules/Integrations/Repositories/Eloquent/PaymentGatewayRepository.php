<?php

namespace App\Modules\Integrations\Repositories\Eloquent;

use App\Modules\Integrations\Enums\GatewayMode;
use App\Modules\Integrations\Enums\GatewayPurpose;
use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Models\PaymentGatewayConfig;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<PaymentGateway>
 */
class PaymentGatewayRepository extends BaseRepository implements PaymentGatewayRepositoryContract
{
    protected function model(): string
    {
        return PaymentGateway::class;
    }

    public function listed(): Collection
    {
        return $this->query()->with('configs')->orderBy('sort_order')->orderBy('id')->get();
    }

    public function findByCode(PaymentGatewayCode $code): ?PaymentGateway
    {
        return $this->query()->with('configs')->where('code', $code->value)->first();
    }

    public function ensure(array $codes): void
    {
        $existing = $this->query()->pluck('code')->map(static fn (mixed $code): string => $code instanceof PaymentGatewayCode ? $code->value : (string) $code)->all();

        foreach ($codes as $index => $code) {
            if (! in_array($code->value, $existing, true)) {
                $this->query()->create(['code' => $code, 'is_active' => false, 'sort_order' => $index]);
            }
        }
    }

    public function configFor(PaymentGateway $gateway, GatewayPurpose $purpose): PaymentGatewayConfig
    {
        $config = $gateway->config($purpose);

        if ($config instanceof PaymentGatewayConfig) {
            return $config;
        }

        return new PaymentGatewayConfig([
            'payment_gateway_id' => $gateway->id,
            'purpose' => $purpose,
            'is_enabled' => false,
            'mode' => GatewayMode::Test,
            'currencies' => [],
        ]);
    }

    public function saveConfig(PaymentGatewayConfig $config, array $attributes): PaymentGatewayConfig
    {
        $config->fill($attributes)->save();

        return $config;
    }

    public function usableFor(GatewayPurpose $purpose): Collection
    {
        return $this->query()
            ->with('configs')
            ->where('is_active', true)
            ->whereHas('configs', static fn ($query) => $query->where('purpose', $purpose->value)->where('is_enabled', true))
            ->orderBy('sort_order')
            ->get();
    }
}
