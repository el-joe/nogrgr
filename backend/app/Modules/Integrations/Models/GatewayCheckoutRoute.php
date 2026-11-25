<?php

namespace App\Modules\Integrations\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central routing of a storefront checkout paid with the platform's gateway keys:
 * gateway + checkout id → store (tenant) + payment attempt reference. Tenant-scoped; the
 * central webhook looks routes up across stores (withoutTenantScope).
 *
 * @property int $id
 * @property string $gateway
 * @property string $checkout_id
 * @property int $tenant_id
 * @property string $payment_reference
 * @property Carbon|null $created_at
 */
class GatewayCheckoutRoute extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'gateway_checkout_routes';

    /**
     * @var list<string>
     */
    protected $fillable = ['gateway', 'checkout_id', 'tenant_id', 'payment_reference'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tenant_id' => 'integer'];
    }
}
