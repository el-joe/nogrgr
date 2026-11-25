<?php

namespace App\Modules\Catalog\Models\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Database\Factories\ManufacturingRequestFactory;
use App\Modules\Catalog\Enums\ManufacturingRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Models\Concerns\HasRequestThread;
use App\Modules\Catalog\Models\Product;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Manufacturing request (ADM-018e/f): private-label / custom production with quote,
 * sampling and payment requests.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $product_id
 * @property string $product_name
 * @property string|null $description
 * @property string|null $reference_url
 * @property int $quantity
 * @property string|null $admin_notes
 * @property-read Product|null $product
 * @property ManufacturingRequestStatus $status
 * @property string|null $submitted_by
 * @property Carbon|null $admin_seen_at
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $admin_unread_count
 */
class ManufacturingRequest extends Model implements ThreadedRequest
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<ManufacturingRequestFactory> */
    use HasFactory;

    use HasRequestThread;

    protected $table = 'catalog_manufacturing_requests';

    protected $guarded = ['id'];

    protected static function newFactory(): ManufacturingRequestFactory
    {
        return ManufacturingRequestFactory::new();
    }

    public function kind(): RequestKind
    {
        return RequestKind::Manufacturing;
    }

    public function subject(): string
    {
        return $this->product_name;
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ManufacturingRequestStatus::class,
            'quantity' => 'integer',
            'admin_seen_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }
}
