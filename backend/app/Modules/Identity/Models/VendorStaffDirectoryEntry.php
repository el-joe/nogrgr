<?php

namespace App\Modules\Identity\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $email_hash
 * @property int $tenant_id
 * @property int $vendor_staff_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VendorStaffDirectoryEntry extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'vendor_staff_directory';

    /**
     * @var list<string>
     */
    protected $fillable = ['email_hash', 'tenant_id', 'vendor_staff_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'vendor_staff_id' => 'integer',
        ];
    }
}
