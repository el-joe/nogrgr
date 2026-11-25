<?php

namespace App\Modules\Localization\Models;

use App\Modules\Localization\Database\Factories\CountryFactory;
use App\Modules\Localization\Enums\Continent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * ISO 3166-1 country (central DB). `name` is translatable (en / ar).
 *
 * @property int $id
 * @property string $iso2
 * @property string $iso3
 * @property string|null $numeric_code
 * @property string $name
 * @property string|null $phone_code
 * @property string|null $currency_code
 * @property string $flag
 * @property Continent|null $continent
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Country extends Model
{
    use CentralConnection;

    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    use HasTranslations;

    protected $table = 'countries';

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['iso2', 'iso3', 'numeric_code', 'name', 'phone_code', 'currency_code', 'flag', 'continent', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'continent' => Continent::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
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
