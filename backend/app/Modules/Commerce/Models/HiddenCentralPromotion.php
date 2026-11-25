<?php

namespace App\Modules\Commerce\Models;

use App\Modules\Commerce\Enums\PromotionKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A central coupon / flash sale the store chose to hide (tenant DB). Hidden central
 * promotions are left out of the store's merged reads.
 *
 * @property int $id
 * @property PromotionKind $kind
 * @property int $central_id
 * @property Carbon|null $created_at
 */
class HiddenCentralPromotion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['kind', 'central_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => PromotionKind::class, 'central_id' => 'integer'];
    }
}
