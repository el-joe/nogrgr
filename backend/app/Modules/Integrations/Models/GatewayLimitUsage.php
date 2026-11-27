<?php

namespace App\Modules\Integrations\Models;

use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A store's volume through the platform account of one gateway in one month (ADM-100c).
 * Central; `tenant_id` is explicit (admin lists every store).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $gateway
 * @property string $period "YYYY-MM"
 * @property int $volume_minor
 * @property Carbon|null $warned_at
 * @property Carbon|null $blocked_at
 * @property Carbon|null $unblocked_at an admin lifted this month's block (no re-block)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 */
class GatewayLimitUsage extends Model
{
    use CentralConnection;

    protected $table = 'gateway_limit_usages';

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'gateway', 'period', 'volume_minor', 'warned_at', 'blocked_at', 'unblocked_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'volume_minor' => 'integer',
            'warned_at' => 'datetime',
            'blocked_at' => 'datetime',
            'unblocked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
