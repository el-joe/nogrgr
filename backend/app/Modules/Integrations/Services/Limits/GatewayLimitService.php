<?php

namespace App\Modules\Integrations\Services\Limits;

use App\Modules\Integrations\Enums\PaymentGatewayCode;
use App\Modules\Integrations\Events\GatewayLimitBlocked;
use App\Modules\Integrations\Events\GatewayLimitWarning;
use App\Modules\Integrations\Models\GatewayLimitUsage;
use App\Modules\Integrations\Models\PaymentGateway;
use App\Modules\Integrations\Repositories\Contracts\GatewayLimitUsageRepositoryContract;
use App\Modules\Integrations\Repositories\Contracts\PaymentGatewayRepositoryContract;
use App\Shared\Services\BaseService;
use Carbon\CarbonInterface;

/**
 * Gateway limits (ADM-100c, decision #124): per gateway, a monthly volume limit (USD
 * cents) for each store paid through the PLATFORM account of the gateway:
 *   usage += paid order total (OrderPaid, payment_source central) per store × month;
 *   usage ≥ warn % (default 80) of the limit → GatewayLimitWarning once a month;
 *   usage ≥ the limit → GatewayLimitBlocked once a month and the gateway leaves the
 *   store's checkout (StorefrontGatewayService) until next month or an admin unblock
 *   (which holds for the rest of that month);
 * stores using their own keys are never counted. No limit = volume only recorded.
 */
class GatewayLimitService extends BaseService
{
    public function __construct(
        private readonly PaymentGatewayRepositoryContract $gateways,
        private readonly GatewayLimitUsageRepositoryContract $usages,
    ) {}

    public static function period(?CarbonInterface $at = null): string
    {
        return ($at ?? now())->format('Y-m');
    }

    public function record(int $tenantId, PaymentGatewayCode $code, int $amountMinor): GatewayLimitUsage
    {
        $usage = $this->usages->add($tenantId, $code->value, self::period(), $amountMinor);
        $gateway = $this->gateways->findByCode($code);
        $limit = $gateway instanceof PaymentGateway ? (int) ($gateway->monthly_store_limit_minor ?? 0) : 0;

        if ($limit <= 0) {
            return $usage;
        }

        if ($usage->volume_minor >= $limit && $usage->unblocked_at === null) {
            $this->usages->mark($usage, 'warned_at');

            if ($this->usages->mark($usage, 'blocked_at')) {
                $this->dispatchAfterCommit(new GatewayLimitBlocked($usage->refresh(), $limit));
            }
        } elseif ($usage->volume_minor < $limit && $usage->volume_minor * 100 >= $limit * self::warnPercent($gateway) && $this->usages->mark($usage, 'warned_at')) {
            $this->dispatchAfterCommit(new GatewayLimitWarning($usage->refresh(), $limit));
        }

        return $usage;
    }

    public function isBlocked(int $tenantId, PaymentGatewayCode $code): bool
    {
        return $this->usages->isBlocked($tenantId, $code->value, self::period());
    }

    /**
     * Saves the limits of every gateway: code => [limit_minor (null = none), warn_percent].
     *
     * @param  array<string, array{limit_minor: int|null, warn_percent: int}>  $limits
     */
    public function updateLimits(array $limits): void
    {
        $this->transaction(function () use ($limits): void {
            foreach ($limits as $code => $row) {
                $gateway = $this->gateways->findByCode(PaymentGatewayCode::from($code));

                if ($gateway instanceof PaymentGateway) {
                    $this->gateways->update($gateway, [
                        'monthly_store_limit_minor' => $row['limit_minor'] !== null && $row['limit_minor'] > 0 ? $row['limit_minor'] : null,
                        'limit_warn_percent' => max(1, min(99, $row['warn_percent'])),
                    ]);
                }
            }
        }, (new PaymentGateway)->getConnectionName());
    }

    public function unblock(GatewayLimitUsage $usage): void
    {
        $this->usages->unblock($usage);
    }

    public static function warnPercent(PaymentGateway $gateway): int
    {
        $percent = (int) $gateway->limit_warn_percent;

        return $percent >= 1 && $percent <= 99 ? $percent : 80;
    }

    /**
     * Usage in percent of the limit (null = no limit).
     */
    public static function percentOf(int $volumeMinor, ?int $limitMinor): ?int
    {
        return $limitMinor !== null && $limitMinor > 0 ? (int) floor($volumeMinor * 100 / $limitMinor) : null;
    }
}
