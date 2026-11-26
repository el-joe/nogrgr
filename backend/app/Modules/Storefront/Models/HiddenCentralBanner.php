<?php

namespace App\Modules\Storefront\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A central (admin) banner the store chose to hide (tenant DB). Left out of BannerCatalog.
 *
 * @property int $id
 * @property int $banner_id
 * @property Carbon|null $created_at
 */
class HiddenCentralBanner extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'hidden_central_banners';

    /**
     * @var list<string>
     */
    protected $fillable = ['banner_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['banner_id' => 'integer'];
    }
}
