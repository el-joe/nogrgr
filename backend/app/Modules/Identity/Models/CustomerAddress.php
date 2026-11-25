<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\CustomerAddressFactory;
use App\Modules\Localization\Models\Country;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Saved customer address (tenant DB). `country_id` points to the central `countries`
 * table; the country name is resolved from it, never stored.
 *
 * @property int $id
 * @property int $customer_id
 * @property string|null $label
 * @property string $full_name
 * @property string|null $email
 * @property string $phone
 * @property string $phone_country
 * @property string $address_line_1
 * @property string $city
 * @property string|null $state
 * @property int $country_id
 * @property string|null $latitude
 * @property string|null $longitude
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Country|null $country
 */
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    protected $table = 'customer_addresses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_id', 'label', 'full_name', 'email', 'phone', 'phone_country', 'address_line_1',
        'city', 'state', 'country_id', 'latitude', 'longitude', 'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'country_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Central country (cross-database relation; eager load with ->with('country')).
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
