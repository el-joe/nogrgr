<?php

namespace App\Modules\Affiliates\Models;

use App\Modules\Affiliates\Enums\PayoutRequestStatus;
use App\Modules\Identity\Models\Affiliate;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * An affiliate asking to be paid (made from the affiliate panel); the admin marks it
 * paid (creates the payout) or rejects it.
 *
 * @property int $id
 * @property int $affiliate_id
 * @property int $amount
 * @property PayoutRequestStatus $status
 * @property string|null $note
 * @property string|null $admin_note
 * @property int|null $payout_id
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property-read Affiliate|null $affiliate
 * @property-read AffiliatePayout|null $payout
 */
class AffiliatePayoutRequest extends Model
{
    use CentralConnection;

    protected $table = 'affiliate_payout_requests';

    /**
     * @var list<string>
     */
    protected $fillable = ['affiliate_id', 'amount', 'status', 'note', 'admin_note', 'payout_id', 'processed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => PayoutRequestStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function money(): Money
    {
        return Money::ofMinor($this->amount);
    }

    public function reference(): string
    {
        return 'PR-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<AffiliatePayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class);
    }
}
