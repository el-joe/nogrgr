<?php

namespace App\Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A store product the customer saved to favorites (tenant DB, SF-ECO-033).
 *
 * @property int $id
 * @property int $customer_id
 * @property int $store_product_id
 * @property Carbon|null $created_at
 */
class CustomerFavorite extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'customer_favorites';

    /**
     * @var list<string>
     */
    protected $fillable = ['customer_id', 'store_product_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['customer_id' => 'integer', 'store_product_id' => 'integer'];
    }
}
