<?php

namespace App\Modules\Platform\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Append-only audit trail (central). In tenant context only that tenant's rows are visible.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string|null $guard
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $url
 * @property Carbon $created_at
 */
class AuditEvent extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
