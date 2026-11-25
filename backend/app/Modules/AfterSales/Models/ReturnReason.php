<?php

namespace App\Modules\AfterSales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A return reason of the platform policy. seller_fault waives the restocking fee and
 * refunds shipping when the whole order comes back; requires_video asks for a video.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $requires_video
 * @property bool $seller_fault
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReturnReason extends Model
{
    use CentralConnection;
    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'name', 'requires_video', 'seller_fault', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requires_video' => 'boolean', 'seller_fault' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
