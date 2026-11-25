<?php

namespace App\Modules\Catalog\Services\Vendor;

use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;

/**
 * CSV export of the store's imported products (VEN-010, same filters as the list): one
 * line per variant × country with the store price from Commerce\Pricing.
 */
class StoreProductExportService
{
    public function __construct(
        private readonly StoreProductRepositoryContract $store,
        private readonly StoreProductPricingService $pricing,
        private readonly StoreCatalogReader $reader,
    ) {}

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $filters
     */
    public function write($stream, array $filters): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['id', 'central_id', 'sku', 'name_en', 'name_ar', 'active', 'featured', 'stock', 'variant', 'country', 'cost_usd', 'fixed_shipping_usd', 'profit_usd', 'price_usd'], escape: '\\');

        foreach ($this->store->exportRows($filters, StoreCatalogReader::relations()) as $product) {
            /** @var StoreProduct $product */
            $view = $this->reader->view($product);
            $name = static fn (string $locale): string => (string) ($view->override?->getTranslation('name', $locale, false) ?: $view->central?->getTranslation('name', $locale, false));
            $base = [
                $product->id,
                $product->central_product_id,
                $view->sku,
                $name('en'),
                $name('ar'),
                $product->is_active ? 'yes' : 'no',
                $product->is_featured ? 'yes' : 'no',
                $view->stock,
            ];
            $rows = $this->pricing->table($product);

            if ($rows === []) {
                fputcsv($stream, [...$base, '', '', '', '', '', ''], escape: '\\');

                continue;
            }

            foreach ($rows as $row) {
                fputcsv($stream, [...$base, $row->variantLabel ?? '', $row->iso2, $row->cost->toDecimal(), $row->shipping->toDecimal(), $row->profit->toDecimal(), $row->price->toDecimal()], escape: '\\');
            }
        }

        fclose($stream);
    }
}
