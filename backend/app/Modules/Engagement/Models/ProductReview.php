<?php

namespace App\Modules\Engagement\Models;

use App\Modules\Commerce\Models\Order;
use App\Modules\Commerce\Models\OrderItem;
use App\Modules\Engagement\Database\Factories\ProductReviewFactory;
use App\Modules\Engagement\Enums\ReviewStatus;
use App\Modules\Identity\Models\Customer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A customer's review of a store product, tied to one delivered order line (tenant DB).
 *
 * @property int $id
 * @property int $store_product_id
 * @property int $customer_id
 * @property int $order_id
 * @property int $order_item_id
 * @property int $rating 1..5
 * @property string|null $title
 * @property string $comment
 * @property string $author_name
 * @property string|null $locale
 * @property ReviewStatus $status
 * @property string|null $moderation_note
 * @property Carbon|null $moderated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Order $order
 * @property-read OrderItem $orderItem
 */
class ProductReview extends Model
{
    /** @use HasFactory<ProductReviewFactory> */
    use HasFactory;

    protected $table = 'product_reviews';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'store_product_id', 'customer_id', 'order_id', 'order_item_id', 'rating', 'title', 'comment', 'author_name',
        'locale', 'status', 'moderation_note', 'moderated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'store_product_id' => 'integer',
            'customer_id' => 'integer',
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'moderated_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProductReviewFactory
    {
        return ProductReviewFactory::new();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
