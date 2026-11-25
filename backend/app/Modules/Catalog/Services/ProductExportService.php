<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Repositories\Contracts\ProductRepositoryContract;

/**
 * CSV exports of the products list (same filters as the table) and of a price list.
 * Written to a stream so large catalogs never sit in memory.
 */
class ProductExportService
{
    public function __construct(private readonly ProductRepositoryContract $products) {}

    /**
     * @param  resource  $stream
     * @param  array<string, mixed>  $filters
     */
    public function write($stream, array $filters): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['id', 'sku', 'name_en', 'name_ar', 'status', 'price_usd', 'compare_at_usd', 'cost_usd', 'stock', 'weight_g', 'categories', 'assignment', 'countries', 'updated_at'], escape: '\\');

        foreach ($this->products->exportRows($filters) as $product) {
            /** @var Product $product */
            $countries = $product->allowed_country_ids;
            fputcsv($stream, [
                $product->id,
                $product->sku,
                $product->getTranslation('name', 'en', false),
                $product->getTranslation('name', 'ar', false),
                $product->status->value,
                $product->costBasis()->toDecimal(),
                $product->compareAtPrice()?->toDecimal(),
                $product->cost_price?->toDecimal(),
                $product->stock,
                $product->weight_grams,
                $product->categories->pluck('name')->implode(' | '),
                $product->assignment_scope->value,
                $countries === null ? 'all' : ($countries === [] ? 'none' : implode(' ', $countries)),
                $product->updated_at?->toIso8601String(),
            ], escape: '\\');
        }

        fclose($stream);
    }

    /**
     * @param  resource  $stream
     * @param  list<array<string, mixed>>  $rows  ProductPriceListService::rows()
     */
    public function writePriceList($stream, array $rows): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['country', 'iso2', 'variant', 'sku', 'weight_g', 'base_usd', 'ship_per_gram_usd', 'fixed_shipping_usd', 'shopper_usd'], escape: '\\');

        foreach ($rows as $row) {
            fputcsv($stream, [
                (string) $row['country']->name,
                $row['country']->iso2,
                $row['variant'] ?? '',
                $row['sku'],
                $row['weight'],
                $row['base']->toDecimal(),
                $row['rate'] ?? '',
                $row['shipping']->toDecimal(),
                $row['shopper']->toDecimal(),
            ], escape: '\\');
        }

        fclose($stream);
    }
}
