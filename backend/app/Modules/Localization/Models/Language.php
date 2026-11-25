<?php

namespace App\Modules\Localization\Models;

use App\Modules\Localization\Database\Factories\LanguageFactory;
use App\Modules\Localization\Enums\TextDirection;
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
 * ISO 639-1 language offered to stores (central DB). ar + en are core: free, active,
 * on for every store and never deletable. Prices are USD minor units.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $native_name
 * @property TextDirection $direction
 * @property string|null $flag
 * @property bool $is_active
 * @property bool $is_free
 * @property bool $is_default
 * @property int $price_minor
 * @property int $ai_translation_price_minor
 * @property bool $offers_ai_translation
 * @property list<int>|null $country_ids
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Language extends Model
{
    use Auditable;
    use CentralConnection;

    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    use HasTranslations;

    /**
     * Languages every store has and nobody can remove.
     */
    public const CORE = ['ar', 'en'];

    public const FALLBACK = 'en';

    protected $table = 'languages';

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code', 'name', 'native_name', 'direction', 'flag', 'is_active', 'is_free', 'is_default',
        'price_minor', 'ai_translation_price_minor', 'offers_ai_translation', 'country_ids', 'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => TextDirection::class,
            'is_active' => 'boolean',
            'is_free' => 'boolean',
            'is_default' => 'boolean',
            'offers_ai_translation' => 'boolean',
            'price_minor' => 'integer',
            'ai_translation_price_minor' => 'integer',
            'country_ids' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function isCore(): bool
    {
        return in_array($this->code, self::CORE, true);
    }

    public function price(): Money
    {
        return Money::ofMinor($this->is_free ? 0 : $this->price_minor);
    }

    public function aiTranslationPrice(): Money
    {
        return Money::ofMinor($this->ai_translation_price_minor);
    }

    public function requiresPayment(): bool
    {
        return ! $this->is_free && $this->price_minor > 0;
    }

    public function isOfferedIn(?int $countryId): bool
    {
        if ($this->country_ids === null || $this->country_ids === []) {
            return true;
        }

        return $countryId !== null && in_array($countryId, array_map('intval', $this->country_ids), true);
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
