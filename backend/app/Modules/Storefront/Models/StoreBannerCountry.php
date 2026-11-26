<?php

namespace App\Modules\Storefront\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Country (central id) a store banner shows in.
 *
 * @property int $store_banner_id
 * @property int $country_id
 */
class StoreBannerCountry extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'store_banner_id';

    protected $table = 'store_banner_countries';

    /**
     * @var list<string>
     */
    protected $fillable = ['store_banner_id', 'country_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['store_banner_id' => 'integer', 'country_id' => 'integer'];
    }
}
