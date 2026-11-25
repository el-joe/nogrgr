<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Country (central id) a store coupon is limited to.
 *
 * @property int $store_coupon_id
 * @property int $country_id
 */
class StoreCouponCountry extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'store_coupon_id';

    /**
     * @var list<string>
     */
    protected $fillable = ['store_coupon_id', 'country_id'];
}
