<?php

namespace App\Modules\Catalog\Models\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Database\Factories\BrandRequestFactory;
use App\Modules\Catalog\Enums\BrandRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Concerns\HasRequestThread;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Brand request (ADM-018g/h): private-label brand creation / registration with payment requests.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $brand_name
 * @property string|null $description
 * @property string|null $website
 * @property string|null $admin_notes
 * @property BrandRequestStatus $status
 * @property string|null $submitted_by
 * @property Carbon|null $admin_seen_at
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $admin_unread_count
 */
class BrandRequest extends Model implements ThreadedRequest
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<BrandRequestFactory> */
    use HasFactory;

    use HasRequestThread;

    protected $table = 'catalog_brand_requests';

    protected $guarded = ['id'];

    protected static function newFactory(): BrandRequestFactory
    {
        return BrandRequestFactory::new();
    }

    public function kind(): RequestKind
    {
        return RequestKind::Brand;
    }

    public function subject(): string
    {
        return $this->brand_name;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BrandRequestStatus::class,
            'admin_seen_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }
}
