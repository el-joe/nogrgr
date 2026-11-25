<?php

namespace App\Modules\AfterSales\Models;

use App\Modules\AfterSales\Enums\ReturnMediaKind;
use App\Modules\Commerce\Enums\ActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Evidence / inspection photo or video of a return on the private `central` disk.
 *
 * @property int $id
 * @property int $return_request_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime
 * @property int $size
 * @property ReturnMediaKind $kind
 * @property ActorType $uploaded_by_type
 * @property int|null $uploaded_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReturnRequestMedia extends Model
{
    use CentralConnection;

    protected $table = 'return_request_media';

    /**
     * @var list<string>
     */
    protected $fillable = ['return_request_id', 'disk', 'path', 'original_name', 'mime', 'size', 'kind', 'uploaded_by_type', 'uploaded_by_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size' => 'integer', 'kind' => ReturnMediaKind::class, 'uploaded_by_type' => ActorType::class, 'uploaded_by_id' => 'integer'];
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
