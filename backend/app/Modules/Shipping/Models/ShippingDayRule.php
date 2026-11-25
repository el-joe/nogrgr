<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Localization\Models\Country;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Delivery duration of a company in one country (ADM-033b): min / max shipping days.
 *
 * @property int $id
 * @property int $shipping_company_id
 * @property int $country_id
 * @property int $min_days
 * @property int $max_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ShippingCompany $company
 * @property-read Country $country
 */
class ShippingDayRule extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = ['shipping_company_id', 'country_id', 'min_days', 'max_days'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shipping_company_id' => 'integer', 'country_id' => 'integer', 'min_days' => 'integer', 'max_days' => 'integer'];
    }

    /**
     * @return BelongsTo<ShippingCompany, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(ShippingCompany::class, 'shipping_company_id');
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
