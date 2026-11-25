<?php

namespace App\Modules\AfterSales\Models;

use App\Modules\AfterSales\Enums\ReturnShippingPayer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * The platform default return policy (ADM-090, one row). Stores may tighten it later.
 *
 * @property int $id
 * @property int $window_days
 * @property int $restocking_fee_bps
 * @property ReturnShippingPayer $return_shipping_payer
 * @property list<int>|null $non_returnable_category_ids
 * @property bool $require_video
 * @property bool $allow_exchanges
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReturnPolicy extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['window_days', 'restocking_fee_bps', 'return_shipping_payer', 'non_returnable_category_ids', 'require_video', 'allow_exchanges'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_days' => 'integer',
            'restocking_fee_bps' => 'integer',
            'return_shipping_payer' => ReturnShippingPayer::class,
            'non_returnable_category_ids' => 'array',
            'require_video' => 'boolean',
            'allow_exchanges' => 'boolean',
        ];
    }
}
