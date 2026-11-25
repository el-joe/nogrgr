<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\DTOs\Vendor\OwnProductData;
use App\Modules\Catalog\Enums\MediaConversionStatus;
use App\Modules\Catalog\Enums\OwnProductSection;
use App\Modules\Catalog\Enums\StoreProductChange;
use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Catalog\Events\Store\OwnProductSaved;
use App\Modules\Catalog\Events\Store\StoreProductsChanged;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Models\StoreProductVariant;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vendor-own products (VEN-016 … 016c): created and priced entirely by the store
 * (ORDER_FORMULAS §1 "own products": cost 0, profit = sell price, no fixed shipping).
 * Gallery on the tenant-suffixed `public` disk (tenant `catalog_media`). Content changes
 * fire OwnProductSaved for the P10 AI translation.
 */
class OwnProductService extends BaseService
{
    public const DISK = 'public';

    public const IMAGE_DIRECTORY = 'products';

    public function __construct(private readonly StoreProductRepositoryContract $store) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<StoreProduct>
     */
    public function listing(array $filters = []): Builder
    {
        return $this->store->ownListing($filters);
    }

    /**
     * @return array<string, Collection<int, StoreProduct>>
     */
    public function sections(int $limit = 3): array
    {
        $sections = [];

        foreach (OwnProductSection::cases() as $section) {
            $sections[$section->value] = $this->store->ownSection($section, $limit);
        }

        return $sections;
    }

    public function count(): int
    {
        return $this->store->query()->own()->count();
    }

    public function find(int $id): StoreProduct
    {
        return $this->store->findOwn($id) ?? throw (new ModelNotFoundException)->setModel(StoreProduct::class, [$id]);
    }

    public function create(OwnProductData $data): StoreProduct
    {
        $product = $this->transaction(function () use ($data): StoreProduct {
            /** @var StoreProduct $product */
            $product = $this->store->create([
                'source' => StoreProductSource::Own,
                'sort_order' => $this->store->nextSortOrder(),
                ...$this->attributes($data, null),
            ]);

            $this->syncChildren($product, $data);

            return $product;
        });

        $this->dispatchAfterCommit(new OwnProductSaved(StoreCatalogService::tenantId(), $product->id, true, $product->aiTranslatableFields()));
        $this->dispatchAfterCommit(new StoreProductsChanged(StoreCatalogService::tenantId(), StoreProductChange::Created, [$product->id]));

        return $product;
    }

    public function update(StoreProduct $product, OwnProductData $data): StoreProduct
    {
        $changed = [];

        $this->transaction(function () use ($product, $data, &$changed): void {
            $product->fill($this->attributes($data, $product));

            foreach ($product->aiTranslatableFields() as $field) {
                if ($product->isDirty($field)) {
                    $changed[] = $field;
                }
            }

            $product->save();
            $this->syncChildren($product, $data);
        });

        $this->dispatchAfterCommit(new OwnProductSaved(StoreCatalogService::tenantId(), $product->id, false, $changed));
        $this->dispatchAfterCommit(new StoreProductsChanged(StoreCatalogService::tenantId(), StoreProductChange::Updated, [$product->id]));

        return $product->refresh();
    }

    public function delete(StoreProduct $product): void
    {
        $paths = $product->media()->get()->pluck('path')->all();
        $id = $product->id;

        $this->transaction(function () use ($product): void {
            $product->media()->delete();
            $product->delete();
        });

        Storage::disk(self::DISK)->delete($paths);
        event(new StoreProductsChanged(StoreCatalogService::tenantId(), StoreProductChange::Removed, [$id]));
    }

    public function setActive(StoreProduct $product, bool $active): StoreProduct
    {
        $product->forceFill(['is_active' => $active])->save();
        event(new StoreProductsChanged(StoreCatalogService::tenantId(), StoreProductChange::Updated, [$product->id]));

        return $product;
    }

    /**
     * Inventory page (VEN-016b): product stock, or per-variant stock (product stock = sum).
     *
     * @param  array<int, int>  $variantStock  variant id => stock
     */
    public function updateStock(StoreProduct $product, ?int $stock, array $variantStock = []): StoreProduct
    {
        $this->transaction(function () use ($product, $stock, $variantStock): void {
            $variants = $product->variants()->get();

            if ($variants->isNotEmpty()) {
                foreach ($variants as $variant) {
                    if (array_key_exists($variant->id, $variantStock)) {
                        $variant->forceFill(['stock' => max(0, $variantStock[$variant->id])])->save();
                    }
                }

                $stock = (int) $product->variants()->sum('stock');
            }

            $product->forceFill(['stock' => max(0, (int) $stock)])->save();
        });

        event(new StoreProductsChanged(StoreCatalogService::tenantId(), StoreProductChange::Updated, [$product->id]));

        return $product->refresh();
    }

    public static function imageUrl(CatalogMedia $media): string
    {
        $tenant = tenant();

        return $tenant instanceof Tenant
            ? StoreSettingsService::storeUrl($tenant).'/tenancy/assets/'.$media->path
            : $media->url();
    }

    public static function primaryImageUrl(StoreProduct $product): ?string
    {
        $media = $product->media->first();

        return $media instanceof CatalogMedia ? self::imageUrl($media) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(OwnProductData $data, ?StoreProduct $existing): array
    {
        $hasVariants = $data->variants !== [];

        return [
            'name' => self::clean($data->name),
            'label' => self::clean($data->label),
            'summary' => self::clean($data->summary),
            'description' => self::clean($data->description),
            'sku' => $data->sku !== null && trim($data->sku) !== '' ? trim($data->sku) : null,
            'slug' => $this->uniqueSlug($data->slug ?: (string) (self::clean($data->name)['en'] ?? (array_values(self::clean($data->name))[0] ?? 'product')), $existing?->id),
            'weight_grams' => max(0, $data->weightGrams),
            'category_ids' => array_values(array_unique(array_map('intval', $data->categoryIds))),
            'country_ids' => $data->countryIds === null ? null : array_values(array_unique(array_map('intval', $data->countryIds))),
            'price' => $data->price,
            'sale_price' => $data->salePrice,
            'cost_price' => $data->costPrice,
            'stock' => $hasVariants ? array_sum(array_map(static fn ($variant): int => $variant->stock, $data->variants)) : max(0, $data->stock),
            'is_active' => $data->isActive,
            'is_featured' => $data->isFeatured,
            'manage_stock' => $data->manageStock,
            'is_taxable' => $data->isTaxable,
        ];
    }

    private function syncChildren(StoreProduct $product, OwnProductData $data): void
    {
        $keep = [];

        foreach (array_values($data->variants) as $position => $row) {
            /** @var StoreProductVariant|null $variant */
            $variant = $row->id !== null ? $product->variants()->whereKey($row->id)->first() : null;
            $attributes = [
                'title' => self::clean($row->title),
                'sku' => $row->sku,
                'price' => $row->price,
                'sale_price' => $row->salePrice,
                'stock' => max(0, $row->stock),
                'is_active' => $row->isActive,
                'position' => $position,
            ];

            $variant = $variant !== null ? tap($variant)->update($attributes) : $product->variants()->create($attributes);
            $keep[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keep)->delete();
        $this->syncImages($product, $data->keepImageIds, $data->images);
    }

    /**
     * @param  list<int>  $keepIds  in display order (first = primary)
     * @param  list<UploadedFile>  $uploads
     */
    private function syncImages(StoreProduct $product, array $keepIds, array $uploads): void
    {
        $existing = $product->media()->get();
        $removed = $existing->whereNotIn('id', $keepIds);
        Storage::disk(self::DISK)->delete($removed->pluck('path')->all());
        $product->media()->whereKey($removed->modelKeys())->delete();

        $order = 0;

        foreach ($keepIds as $id) {
            $product->media()->whereKey($id)->update(['sort_order' => $order, 'is_primary' => $order === 0]);
            $order++;
        }

        foreach ($uploads as $upload) {
            $path = $upload->storeAs(self::IMAGE_DIRECTORY.'/'.$product->id, Str::uuid()->toString().'.'.strtolower($upload->guessExtension() ?: 'jpg'), self::DISK);

            if (! is_string($path)) {
                continue;
            }

            $product->media()->create([
                'disk' => self::DISK,
                'path' => $path,
                'mime_type' => $upload->getMimeType(),
                'size' => (int) $upload->getSize(),
                'conversion_status' => MediaConversionStatus::Skipped,
                'is_primary' => $order === 0,
                'sort_order' => $order,
            ]);
            $order++;
        }
    }

    private function uniqueSlug(string $source, ?int $ignoreId): string
    {
        $base = Str::slug($source) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while ($this->store->query()->where('slug', $slug)->when($ignoreId !== null, static fn (Builder $query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return array<string, string>
     */
    private static function clean(array $values): array
    {
        return array_filter(array_map(static fn (?string $value): string => trim((string) $value), $values), static fn (string $value): bool => $value !== '');
    }
}
