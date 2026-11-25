<?php

namespace App\Modules\Localization\Models;

use App\Modules\Localization\Database\Factories\CityFactory;
use App\Modules\Localization\Enums\CityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * City suggestion of a country (central DB). `name` is translatable (en / ar).
 *
 * @property int $id
 * @property int $country_id
 * @property string $slug
 * @property string $name
 * @property CityType $type
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class City extends Model
{
    use CentralConnection;

    /** @use HasFactory<CityFactory> */
    use HasFactory;

    use HasTranslations;

    protected $table = 'cities';

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['country_id', 'slug', 'name', 'type', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'type' => CityType::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
