<?php

namespace App\Modules\Affiliates\Models;

use App\Modules\Affiliates\Enums\PayoutMethod;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Money sent to an affiliate (allocated oldest-first to approved conversions).
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int $amount
 * @property PayoutMethod $method
 * @property string|null $reference
 * @property string|null $notes
 * @property string|null $attachment
 * @property string|null $attachment_name
 * @property int|null $paid_by_id
 * @property string|null $paid_by_name
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property-read Affiliate|null $affiliate
 */
class AffiliatePayout extends Model
{
    use CentralConnection;

    protected $table = 'affiliate_payouts';

    /**
     * @var list<string>
     */
    protected $fillable = ['affiliate_id', 'amount', 'method', 'reference', 'notes', 'attachment', 'attachment_name', 'paid_by_id', 'paid_by_name', 'paid_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'method' => PayoutMethod::class,
            'paid_at' => 'datetime',
        ];
    }

    public function money(): Money
    {
        return Money::ofMinor($this->amount);
    }

    public function displayReference(): string
    {
        return 'AP-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
