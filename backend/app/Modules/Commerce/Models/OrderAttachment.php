<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\AttachmentKind;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A fulfilment file of a store order (label, packing proof, customs) on the private
 * `central` disk, streamed only through the admin download route.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $order_uuid
 * @property AttachmentKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property int|null $uploaded_by_id
 * @property string|null $uploaded_by_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrderAttachment extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['tenant_id', 'order_uuid', 'kind', 'disk', 'path', 'original_name', 'mime', 'size', 'uploaded_by_id', 'uploaded_by_name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tenant_id' => 'integer', 'kind' => AttachmentKind::class, 'size' => 'integer', 'uploaded_by_id' => 'integer'];
    }
}
