<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\PlanLimit;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * An admin override of one plan quota for one store (central DB, tenant scoped).
 * value null = unlimited; no row = the plan value applies (EffectivePlanLimits).
 *
 * @property int $id
 * @property int $tenant_id
 * @property PlanLimit $limit
 * @property int|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlanLimitOverride extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'tenant_plan_limit_overrides';

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'limit', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'limit' => PlanLimit::class,
            'value' => 'integer',
        ];
    }
}
