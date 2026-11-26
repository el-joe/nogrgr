<?php

namespace App\Modules\Affiliates\Models;

use App\Modules\Identity\Models\Affiliate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One-time affiliate sign-in token issued from the vendor panel (AFF-007). Only the
 * SHA-256 hash of the token is stored.
 *
 * @property int $id
 * @property int $affiliate_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property Carbon|null $created_at
 */
class AffiliateSsoToken extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'affiliate_sso_tokens';

    /**
     * @var list<string>
     */
    protected $fillable = ['affiliate_id', 'token_hash', 'expires_at', 'used_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
