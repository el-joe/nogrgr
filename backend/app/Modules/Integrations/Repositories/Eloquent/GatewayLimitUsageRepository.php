<?php

namespace App\Modules\Integrations\Repositories\Eloquent;

use App\Modules\Integrations\Models\GatewayLimitUsage;
use App\Modules\Integrations\Repositories\Contracts\GatewayLimitUsageRepositoryContract;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * @extends BaseRepository<GatewayLimitUsage>
 */
class GatewayLimitUsageRepository extends BaseRepository implements GatewayLimitUsageRepositoryContract
{
    protected function model(): string
    {
        return GatewayLimitUsage::class;
    }

    public function add(int $tenantId, string $gateway, string $period, int $amountMinor): GatewayLimitUsage
    {
        $keys = ['tenant_id' => $tenantId, 'gateway' => $gateway, 'period' => $period];
        $this->query()->insertOrIgnore($keys + ['volume_minor' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->query()->where($keys)->update(['volume_minor' => DB::raw('volume_minor + '.max(0, $amountMinor)), 'updated_at' => now()]);

        /** @var GatewayLimitUsage $usage */
        $usage = $this->query()->where($keys)->firstOrFail();

        return $usage;
    }

    public function mark(GatewayLimitUsage $usage, string $column): bool
    {
        if (! in_array($column, ['warned_at', 'blocked_at'], true)) {
            throw new InvalidArgumentException('Unknown limit mark '.$column);
        }

        return $this->query()->whereKey($usage->id)->whereNull($column)->update([$column => now()]) === 1;
    }

    public function isBlocked(int $tenantId, string $gateway, string $period): bool
    {
        return $this->query()->where(['tenant_id' => $tenantId, 'gateway' => $gateway, 'period' => $period])->whereNotNull('blocked_at')->exists();
    }

    public function unblock(GatewayLimitUsage $usage): void
    {
        $this->query()->whereKey($usage->id)->update(['blocked_at' => null, 'unblocked_at' => now(), 'updated_at' => now()]);
    }

    public function listing(string $period, array $filters = []): Builder
    {
        $query = $this->query()->with('tenant')->where('period', $period);

        if (is_string($filters['gateway'] ?? null) && $filters['gateway'] !== '') {
            $query->where('gateway', $filters['gateway']);
        }

        match ($filters['state'] ?? null) {
            'blocked' => $query->whereNotNull('blocked_at'),
            'warned' => $query->whereNotNull('warned_at')->whereNull('blocked_at'),
            default => null,
        };

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->whereHas('tenant', fn (Builder $tenant) => $tenant->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%'));
        }

        return $query;
    }
}
