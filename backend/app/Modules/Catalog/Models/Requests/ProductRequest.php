<?php

namespace App\Modules\Catalog\Models\Requests;

use App\Modules\Catalog\Contracts\ThreadedRequest;
use App\Modules\Catalog\Database\Factories\ProductRequestFactory;
use App\Modules\Catalog\Enums\ProductRequestStatus;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Enums\RequestPriority;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Concerns\HasRequestThread;
use App\Shared\Money\Money;
use App\Shared\Money\MoneyCast;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Product request (ADM-018c/d): a vendor asks the platform to source a new product.
 * Central table, target price in USD minor units.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $title
 * @property string $description
 * @property string|null $product_url
 * @property int|null $category_id
 * @property Money|null $target_price
 * @property RequestPriority $priority
 * @property Carbon|null $archived_at
 * @property-read Category|null $category
 * @property ProductRequestStatus $status
 * @property string|null $submitted_by
 * @property Carbon|null $admin_seen_at
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $admin_unread_count
 */
class ProductRequest extends Model implements ThreadedRequest
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<ProductRequestFactory> */
    use HasFactory;

    use HasRequestThread;

    protected $table = 'catalog_product_requests';

    protected $guarded = ['id'];

    protected static function newFactory(): ProductRequestFactory
    {
        return ProductRequestFactory::new();
    }

    public function kind(): RequestKind
    {
        return RequestKind::Product;
    }

    public function subject(): string
    {
        return $this->title;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductRequestStatus::class,
            'priority' => RequestPriority::class,
            'target_price' => MoneyCast::class.':USD',
            'admin_seen_at' => 'datetime',
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
