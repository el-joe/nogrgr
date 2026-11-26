<?php

namespace App\Modules\Affiliates\Models;

use App\Modules\Identity\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One `?ref=` visit of the central website (cookie holds its id).
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int|null $promo_code_id
 * @property int|null $tenant_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $landing_url
 * @property Carbon|null $converted_at
 * @property Carbon|null $created_at
 */
class AffiliateClick extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'affiliate_clicks';

    /**
     * @var list<string>
     */
    protected $fillable = ['affiliate_id', 'promo_code_id', 'tenant_id', 'ip', 'user_agent', 'landing_url', 'converted_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
