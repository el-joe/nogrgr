<?php

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Database\Factories\PlanFactory;
use App\Modules\Billing\Enums\BillingTerm;
use App\Modules\Billing\Enums\PlanFeature;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Billing\Enums\PlanStatus;
use App\Modules\Platform\Concerns\Auditable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Subscription plan (central DB). Prices in USD minor units per BillingTerm.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $highlights
 * @property string|null $icon
 * @property PlanStatus $status
 * @property int $monthly_price_minor
 * @property int $yearly_price_minor
 * @property string $currency
 * @property int $trial_days
 * @property int $sort_order
 * @property array<string, int|null>|null $limits
 * @property list<string>|null $features
 * @property int|null $open_subscriptions_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Plan extends Model
{
    use Auditable;
    use CentralConnection;

    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    use HasTranslations;

    protected $table = 'plans';

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'description', 'highlights'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name', 'description', 'highlights', 'icon', 'status', 'monthly_price_minor', 'yearly_price_minor',
        'currency', 'trial_days', 'sort_order', 'limits', 'features',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'monthly_price_minor' => 'integer',
            'yearly_price_minor' => 'integer',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
            'limits' => 'array',
            'features' => 'array',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function priceFor(BillingTerm $term): Money
    {
        return Money::ofMinor($term === BillingTerm::Yearly ? $this->yearly_price_minor : $this->monthly_price_minor, $this->currency);
    }

    /**
     * Null = unlimited.
     */
    public function limit(PlanLimit $limit): ?int
    {
        $value = ($this->limits ?? [])[$limit->value] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(PlanFeature $feature): bool
    {
        return in_array($feature->value, $this->features ?? [], true);
    }

    /**
     * Highlights (one per line) in the current locale.
     *
     * @return list<string>
     */
    public function highlightLines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $this->highlights) ?: [])));
    }
}
