<?php

namespace App\Modules\Ai\Support;

use App\Modules\Ai\Contracts\AiTranslatable;
use App\Modules\Ai\Contracts\StoreTranslationSource;
use App\Modules\Ai\Enums\StoreTranslationSection;
use App\Modules\Ai\Repositories\Translation\CentralProductOverrideSource;
use App\Modules\Ai\Repositories\Translation\CentralVariantOverrideSource;
use App\Modules\Ai\Repositories\Translation\ModelTranslationSource;
use App\Modules\Ai\Repositories\Translation\StaticTextsSource;
use App\Modules\Ai\Repositories\Translation\StoreSettingsTextSource;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Models\StoreCategory;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Commerce\Models\StoreFlashSale;
use App\Modules\Localization\Repositories\Contracts\ContentTranslationLockRepositoryContract;
use App\Modules\Notifications\Models\StoreEmailTemplate;
use App\Modules\Storefront\Models\StoreBanner;
use App\Modules\Storefront\Models\StoreMenuLink;
use App\Modules\Storefront\Models\StorePage;
use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Translatable registry of the paid full-store translation (decision #44): every
 * section of a store, in run order. A store-owned (tenant DB) model with translatable
 * columns must be covered here — tests/Unit/Architecture/StoreTranslationRegistryTest
 * fails otherwise, so nothing is skipped silently.
 */
class StoreTranslationRegistry
{
    /**
     * @var array<string, StoreTranslationSource>|null
     */
    private ?array $sources = null;

    public function __construct(private readonly Container $container) {}

    /**
     * @return array<string, StoreTranslationSource> section value => source
     */
    public function sources(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $locks = $this->container->make(ContentTranslationLockRepositoryContract::class);
        $model = fn (StoreTranslationSection $section, string $class, ?Closure $query = null): ModelTranslationSource => new ModelTranslationSource($section, $class, $locks, $query);

        $sources = [
            $model(StoreTranslationSection::Categories, StoreCategory::class),
            $model(StoreTranslationSection::Products, StoreProduct::class, static fn (): Builder => StoreProduct::query()->where('source', StoreProductSource::Own->value)),
            $model(StoreTranslationSection::Variants, StoreProductVariant::class, static fn (): Builder => StoreProductVariant::query()
                ->whereHas('product', static fn (Builder $query): Builder => $query->where('source', StoreProductSource::Own->value))),
            $this->container->make(CentralProductOverrideSource::class),
            $this->container->make(CentralVariantOverrideSource::class),
            $model(StoreTranslationSection::FlashSales, StoreFlashSale::class),
            $model(StoreTranslationSection::Banners, StoreBanner::class),
            $model(StoreTranslationSection::MenuLinks, StoreMenuLink::class),
            $model(StoreTranslationSection::Pages, StorePage::class),
            $model(StoreTranslationSection::EmailTemplates, StoreEmailTemplate::class),
            $this->container->make(StoreSettingsTextSource::class),
            $this->container->make(StaticTextsSource::class),
        ];

        $this->sources = [];

        foreach ($sources as $source) {
            $this->sources[$source->section()->value] = $source;
        }

        return $this->sources;
    }

    public function source(StoreTranslationSection $section): StoreTranslationSource
    {
        return $this->sources()[$section->value] ?? throw new InvalidArgumentException('No translation source for '.$section->value.'.');
    }

    /**
     * Every model the sources translate.
     *
     * @return list<class-string<Model&AiTranslatable>>
     */
    public function models(): array
    {
        $models = [];

        foreach ($this->sources() as $source) {
            array_push($models, ...$source->models());
        }

        return array_values(array_unique($models));
    }
}
