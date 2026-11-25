<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Localization\Models\Country;
use App\Modules\Shipping\Database\Factories\ShippingCompanyFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Shipping company (ADM-033): logo, contact, tracking URL pattern with a {tracking}
 * placeholder, active flag; day rules per country and stop periods.
 *
 * @property int $id
 * @property string $name
 * @property string|null $logo_path
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $website
 * @property string|null $tracking_url_pattern
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ShippingDayRule> $dayRules
 * @property-read Collection<int, ShippingStopPeriod> $stopPeriods
 * @property-read Collection<int, Country> $defaultCountries
 * @property-read int|null $day_rules_count
 * @property-read int|null $stop_periods_count
 */
class ShippingCompany extends Model
{
    use CentralConnection;

    /** @use HasFactory<ShippingCompanyFactory> */
    use HasFactory;

    public const TRACKING_PLACEHOLDER = '{tracking}';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'logo_path', 'contact_email', 'contact_phone', 'website', 'tracking_url_pattern', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<ShippingDayRule, $this>
     */
    public function dayRules(): HasMany
    {
        return $this->hasMany(ShippingDayRule::class);
    }

    /**
     * @return HasMany<ShippingStopPeriod, $this>
     */
    public function stopPeriods(): HasMany
    {
        return $this->hasMany(ShippingStopPeriod::class);
    }

    /**
     * Countries where this company is the default carrier.
     *
     * @return BelongsToMany<Country, $this>
     */
    public function defaultCountries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class, 'shipping_default_companies');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path !== null ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Tracking link of a shipment, or null when the company has no pattern.
     */
    public function trackingUrl(string $trackingNumber): ?string
    {
        if ($this->tracking_url_pattern === null || $this->tracking_url_pattern === '') {
            return null;
        }

        return str_replace(self::TRACKING_PLACEHOLDER, rawurlencode($trackingNumber), $this->tracking_url_pattern);
    }
}
