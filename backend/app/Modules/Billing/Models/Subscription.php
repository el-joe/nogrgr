<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\SubscriptionFactory;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PaymentGateway;
use App\Modules\Billing\Enums\SubscriptionKind;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Platform\Concerns\Auditable;
use App\Shared\Money\Money;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A store's plan subscription (central DB, tenant scoped). Amount = the term price in USD minor units.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $plan_id
 * @property BillingTerm $term
 * @property SubscriptionStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property PaymentGateway|null $gateway
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_starts_at
 * @property Carbon|null $current_period_ends_at
 * @property int $renewals_count
 * @property bool $auto_renew
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $expired_at
 * @property int|null $assigned_by_admin_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan $plan
 * @property-read Invoice|null $latestInvoice
 */
class Subscription extends Model
{
    use Auditable;
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $table = 'subscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id', 'plan_id', 'term', 'status', 'amount_minor', 'currency', 'gateway', 'trial_ends_at',
        'current_period_starts_at', 'current_period_ends_at', 'renewals_count', 'auto_renew',
        'cancelled_at', 'expired_at', 'assigned_by_admin_id', 'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => BillingTerm::class,
            'status' => SubscriptionStatus::class,
            'gateway' => PaymentGateway::class,
            'amount_minor' => 'integer',
            'renewals_count' => 'integer',
            'auto_renew' => 'boolean',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return MorphMany<Invoice, $this>
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'billable');
    }

    /**
     * @return MorphOne<Invoice, $this>
     */
    public function latestInvoice(): MorphOne
    {
        return $this->morphOne(Invoice::class, 'billable')->latestOfMany();
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    public function kind(): SubscriptionKind
    {
        return match (true) {
            $this->status === SubscriptionStatus::Trial => SubscriptionKind::Trial,
            $this->renewals_count > 0 => SubscriptionKind::Renewal,
            default => SubscriptionKind::Registration,
        };
    }

    /**
     * Next billing date (end of the trial or of the current period).
     */
    public function nextBillingAt(): ?Carbon
    {
        if (! $this->status->isOpen() || ! $this->auto_renew) {
            return null;
        }

        return $this->status === SubscriptionStatus::Trial ? $this->trial_ends_at : $this->current_period_ends_at;
    }
}
