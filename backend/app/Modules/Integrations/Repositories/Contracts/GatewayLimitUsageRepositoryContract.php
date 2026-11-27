<?php

namespace App\Modules\Integrations\Repositories\Contracts;

use App\Modules\Integrations\Models\GatewayLimitUsage;
use App\Shared\Repositories\Contracts\RepositoryContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Store volume per platform gateway and month (central, ADM-100c).
 *
 * @extends RepositoryContract<GatewayLimitUsage>
 */
interface GatewayLimitUsageRepositoryContract extends RepositoryContract
{
    /**
     * Adds $amountMinor atomically and returns the fresh row.
     */
    public function add(int $tenantId, string $gateway, string $period, int $amountMinor): GatewayLimitUsage;

    /**
     * Sets warned_at / blocked_at once; true for the call that set it.
     */
    public function mark(GatewayLimitUsage $usage, string $column): bool;

    public function isBlocked(int $tenantId, string $gateway, string $period): bool;

    public function unblock(GatewayLimitUsage $usage): void;

    /**
     * Admin usage list of a month. Filters: gateway, state (warned | blocked), search (store).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<GatewayLimitUsage>
     */
    public function listing(string $period, array $filters = []): Builder;
}
