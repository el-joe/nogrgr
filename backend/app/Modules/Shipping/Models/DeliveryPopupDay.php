<?php

namespace App\Modules\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Share (%) of orders delivered on day N (1–10) after purchase (ADM-035).
 * country_key = country id, 0 = the default for every country without its own rows.
 *
 * @property int $id
 * @property int $country_key
 * @property int $day_number
 * @property int $percentage
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DeliveryPopupDay extends Model
{
    use CentralConnection;

    public const ALL_COUNTRIES = 0;

    /**
     * @var list<string>
     */
    protected $fillable = ['country_key', 'day_number', 'percentage'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['country_key' => 'integer', 'day_number' => 'integer', 'percentage' => 'integer'];
    }
}
