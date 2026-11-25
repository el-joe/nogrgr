<?php

namespace App\Modules\Localization\Models;

use App\Modules\Localization\Database\Factories\CurrencyFactory;
use App\Modules\Platform\Concerns\Auditable;
use App\Shared\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * ISO 4217 currency (central DB). USD is the base (rate 1); every stored amount is
 * USD, other currencies are display-only: `rate` = units per 1 USD (6 decimals).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $decimals
 * @property string $rate
 * @property bool $is_active
 * @property bool $is_base
 * @property Carbon|null $rate_synced_at
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Currency extends Model
{
    use Auditable;
    use CentralConnection;

    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    use HasTranslations;

    protected $table = 'currencies';

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'name', 'symbol', 'decimals', 'rate', 'is_active', 'is_base', 'rate_synced_at', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'rate' => 'decimal:6',
            'is_active' => 'boolean',
            'is_base' => 'boolean',
            'rate_synced_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function isBase(): bool
    {
        return $this->is_base || $this->code === Money::BASE_CURRENCY;
    }

    /**
     * Display conversion of a USD amount into this currency (rounded to its decimals).
     */
    public function convertFromBase(Money $amount): float
    {
        return round($amount->toFloat() * (float) $this->rate, $this->decimals);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
