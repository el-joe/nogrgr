<?php

namespace App\Modules\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Country (central id) a store flash sale is limited to.
 *
 * @property int $store_flash_sale_id
 * @property int $country_id
 */
class StoreFlashSaleCountry extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'store_flash_sale_id';

    /**
     * @var list<string>
     */
    protected $fillable = ['store_flash_sale_id', 'country_id'];
}
