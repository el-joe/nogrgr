<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Storefront\Enums\SocialNetwork;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Store social profile (tenant DB, VEN-034f): one per network, shown as an icon in the
 * storefront footer.
 *
 * @property int $id
 * @property SocialNetwork $network
 * @property string $url
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreSocialLink extends Model
{
    protected $table = 'store_social_links';

    /**
     * @var list<string>
     */
    protected $fillable = ['network', 'url', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'network' => SocialNetwork::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
