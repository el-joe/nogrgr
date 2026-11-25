<?php

namespace App\Modules\Commerce\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Internal admin note on a store order (never shown to the store or the customer).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $order_uuid
 * @property string $note
 * @property int|null $admin_id
 * @property string|null $admin_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrderAdminNote extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'order_uuid', 'note', 'admin_id', 'admin_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'admin_id' => 'integer'];
    }
}
