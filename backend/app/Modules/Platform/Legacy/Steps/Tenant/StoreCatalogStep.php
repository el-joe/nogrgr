<?php

namespace App\Modules\Platform\Legacy\Steps\Tenant;

use App\Modules\Catalog\Enums\StoreProductSource;
use App\Modules\Platform\Legacy\Enums\LegacyIssueLevel;
use App\Modules\Platform\Legacy\Enums\LegacyStep;
use App\Modules\Platform\Legacy\Steps\LegacyStepHandler;
use App\Modules\Platform\Legacy\Support\LegacyContext;
use App\Modules\Platform\Legacy\Support\LegacyTranslations;
use App\Modules\Platform\Legacy\Support\LegacyUnique;
use App\Modules\Platform\Legacy\Support\LegacyValues;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The store's catalogue WITHOUT copying central data (decision #65):
 *  - central selections (old tenant `products` rows with `central_product_id`, and
 *    `tenant_product_overrides` of the dedup era) → `store_products` source central:
 *    only the central id + store business data (active, featured, sort, markup per
 *    country, variant markup per central variant);
 *  - approved content overrides (`tenant_field_overrides`, weight override) →
 *    `product_overrides` keyed by the central id;
 *  - vendor-own products (`is_own_product`) → `store_products` source own + own variants.
 */
class StoreCatalogStep implements LegacyStepHandler
{
    private const OVERRIDE_FIELDS = ['name', 'summary', 'description', 'meta_title', 'meta_description'];

    public function step(): LegacyStep
    {
        return LegacyStep::StoreCatalog;
    }

    public function run(LegacyContext $context): void
    {
        $source = $context->storeSource();

        if ($context->source->hasTable($source, 'products')) {
            $source->table('products')->orderBy('id')->chunkById(200, function (Collection $rows) use ($context, $source): void {
                $ids = $rows->pluck('id')->map('intval')->all();
                $variants = $context->source->hasTable($source, 'product_variants') ? $source->table('product_variants')->whereIn('product_id', $ids)->orderBy('id')->get()->groupBy('product_id') : collect();
                $texts = $context->storeTranslations()->load('App\\Models\\Tenant\\Product', $ids);

                $context->storeWriter()->transaction(function () use ($rows, $context, $variants, $texts): void {
                    foreach ($rows as $row) {
                        $context->report->read($context->scope(), 'products');
                        $own = (bool) ($row->is_own_product ?? false) || $row->central_product_id === null;

                        $own
                            ? $this->ownProduct($context, $row, $variants->get($row->id, collect()), $texts)
                            : $this->centralSelection($context, (int) $row->central_product_id, $row, $variants->get($row->id, collect()), (int) $row->id);
                    }
                });
            });
        }

        if ($context->source->hasTable($source, 'tenant_product_overrides')) {
            $variantRows = $context->source->hasTable($source, 'tenant_product_variant_overrides') ? $source->table('tenant_product_variant_overrides')->get()->groupBy('central_product_id') : collect();

            foreach ($source->table('tenant_product_overrides')->orderBy('central_product_id')->get() as $row) {
                $context->report->read($context->scope(), 'product_overrides');
                $this->centralSelection($context, (int) $row->central_product_id, $row, $variantRows->get($row->central_product_id, collect()), null);
            }
        }

        $this->fieldOverrides($context);
    }

    private function centralSelection(LegacyContext $context, int $oldCentralId, object $row, Collection $variants, ?int $tenantProductId): void
    {
        $writer = $context->storeWriter();
        $centralId = $context->central->id('product', $oldCentralId);

        if ($centralId === null) {
            $context->report->issue(LegacyIssueLevel::Error, $context->scope(), 'store_products', $oldCentralId, 'Selected central product was not migrated (orphan, skipped)');
            $context->report->skipped($context->scope(), 'store_products');

            return;
        }

        $variantMarkup = [];

        foreach ($variants as $variant) {
            $oldVariant = (int) ($variant->central_product_variant_id ?? $variant->central_variant_id ?? 0);
            $newVariant = $oldVariant > 0 ? $context->central->id('variant', $oldVariant) : null;

            if ($newVariant === null) {
                continue;
            }

            if (isset($variant->id) && $tenantProductId !== null) {
                $writer->link('tenant_variant', (int) $variant->id, $newVariant);
            }

            $profit = $this->markup($context, LegacyValues::json($variant->profit ?? null));

            if ($profit !== null && $profit !== []) {
                $variantMarkup[(string) $newVariant] = $profit;
            }
        }

        $attributes = [
            'source' => StoreProductSource::Central->value,
            'central_product_id' => $centralId,
            'markup' => $this->markup($context, LegacyValues::json($row->profit ?? null)),
            'variant_markup' => $variantMarkup === [] ? null : $variantMarkup,
            'imported_at' => $row->adopted_at ?? $row->created_at ?? now(),
            'created_at' => $row->created_at ?? null,
        ];

        foreach (['active' => 'is_active', 'featured' => 'is_featured'] as $old => $new) {
            if (isset($row->{$old})) {
                $attributes[$new] = (bool) $row->{$old};
            }
        }

        if (isset($row->order_number)) {
            $attributes['sort_order'] = (int) $row->order_number;
        }

        $id = $writer->upsert('store_product', 'c'.$oldCentralId, 'store_products', $attributes, ['central_product_id' => $centralId]);

        if ($tenantProductId !== null) {
            $writer->link('tenant_product', $tenantProductId, $id);
        }

        if (isset($row->weight_grams_override) && $row->weight_grams_override !== null) {
            $writer->upsert('product_override', 'c'.$oldCentralId, 'product_overrides', ['product_id' => $centralId, 'weight_grams' => (int) $row->weight_grams_override], ['product_id' => $centralId]);
        }
    }

    /**
     * @param  array<int, array<string, array<string, string>>>  $texts
     */
    private function ownProduct(LegacyContext $context, object $row, Collection $variants, array $texts): void
    {
        $writer = $context->storeWriter();
        $id = (int) $row->id;
        $ownId = $writer->id('store_product', 'o'.$id);
        $name = LegacyTranslations::field($texts, $id, 'name') ?? ['en' => (string) ($row->sku ?? 'Product '.$id)];
        $price = $this->decimalOrDefault($row->price ?? null) ?? $this->decimalOrDefault($row->default_price ?? null);
        $sale = $this->decimalOrDefault($row->sale_price ?? null);

        $newId = $writer->upsert('store_product', 'o'.$id, 'store_products', [
            'source' => StoreProductSource::Own->value,
            'central_product_id' => null,
            'name' => $name,
            'summary' => LegacyTranslations::field($texts, $id, 'summary'),
            'description' => LegacyTranslations::field($texts, $id, 'description'),
            'meta_description' => LegacyTranslations::field($texts, $id, 'meta_description'),
            'slug' => LegacyUnique::value($writer, 'store_products', 'slug', Str::slug((string) ($row->slug ?? '')) ?: 'product-'.$id, $ownId, $id, 255),
            'sku' => $row->sku !== null ? mb_substr((string) $row->sku, 0, 64) : null,
            'is_active' => (bool) ($row->active ?? true),
            'is_featured' => (bool) ($row->featured ?? false),
            'sort_order' => (int) ($row->order_number ?? 0),
            'price' => LegacyValues::minorOrNull($price),
            'sale_price' => $sale !== null && (float) $sale > 0 ? LegacyValues::minor($sale) : null,
            'cost_price' => LegacyValues::minorOrNull($row->cost_price ?? null),
            'stock' => $row->stock ?? null,
            'min_stock' => (int) ($row->min_stock ?? 0),
            'manage_stock' => (bool) ($row->manage_stock ?? true),
            'is_taxable' => (bool) ($row->is_taxable ?? true),
            'weight_grams' => $row->weight_grams ?? null,
            'country_ids' => isset($row->allowed_country_ids) ? $this->countries($context, LegacyValues::json($row->allowed_country_ids)) : null,
            'sold_count' => (int) ($row->orders_count ?? 0),
            'created_at' => $row->created_at,
        ]);

        $writer->link('tenant_product', $id, $newId);

        foreach ($variants->values() as $position => $variant) {
            $variantId = $writer->upsert('store_variant', (int) $variant->id, 'store_product_variants', [
                'store_product_id' => $newId,
                'title' => $variant->title !== null ? ['en' => (string) $variant->title] : null,
                'sku' => $variant->sku !== null ? mb_substr((string) $variant->sku, 0, 64) : null,
                'price' => LegacyValues::minorOrNull($this->decimalOrDefault($variant->sell_price ?? null)),
                'stock' => $variant->stock ?? null,
                'weight_grams' => $variant->weight_grams ?? null,
                'is_active' => (bool) ($variant->active ?? true),
                'position' => $position,
                'created_at' => $variant->created_at,
            ]);
            $writer->link('tenant_variant', (int) $variant->id, $variantId);
        }
    }

    /**
     * Approved central-content overrides → product_overrides / variant_overrides (per field, per locale).
     */
    private function fieldOverrides(LegacyContext $context): void
    {
        $source = $context->storeSource();

        if (! $context->source->hasTable($source, 'tenant_field_overrides')) {
            return;
        }

        $grouped = [];

        foreach ($source->table('tenant_field_overrides')->orderBy('id')->get() as $row) {
            $context->report->read($context->scope(), 'field_overrides');

            if (! in_array($row->field, self::OVERRIDE_FIELDS, true) || ! str_contains(strtolower((string) $row->entity_type), 'product') || str_contains(strtolower((string) $row->entity_type), 'variant')) {
                $context->report->skipped($context->scope(), 'field_overrides');

                continue;
            }

            $grouped[(int) $row->entity_id][(string) $row->field][(string) ($row->locale ?: 'en')] = (string) $row->value;
        }

        foreach ($grouped as $oldCentralId => $fields) {
            $centralId = $context->central->id('product', $oldCentralId);

            if ($centralId === null) {
                $context->report->issue(LegacyIssueLevel::Warning, $context->scope(), 'field_overrides', $oldCentralId, 'Override of a central product that was not migrated (dropped)');

                continue;
            }

            $context->storeWriter()->upsert('product_override', 'c'.$oldCentralId, 'product_overrides', ['product_id' => $centralId] + $fields, ['product_id' => $centralId]);
        }
    }

    /**
     * Old profit JSON {country_id|default: {profit_type, profit_value}} re-keyed to new country ids.
     *
     * @param  array<mixed>|null  $profit
     * @return array<string, array{profit_type: string, profit_value: float}>|null
     */
    private function markup(LegacyContext $context, ?array $profit): ?array
    {
        if ($profit === null) {
            return null;
        }

        $out = [];

        foreach ($profit as $country => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $key = $country === 'default' ? 'default' : $context->central->id('country', (int) $country);

            if ($key === null) {
                continue;
            }

            $out[(string) $key] = [
                'profit_type' => ($rule['profit_type'] ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage',
                'profit_value' => (float) ($rule['profit_value'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Old own prices were moved to JSON per country ({"default": x, "<country>": y}); take the default.
     */
    private function decimalOrDefault(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        $json = LegacyValues::json($value);
        $default = $json['default'] ?? (is_array($json) ? reset($json) : null);

        return is_numeric($default) ? (string) $default : null;
    }

    /**
     * @param  array<mixed>|null  $ids
     * @return list<int>|null
     */
    private function countries(LegacyContext $context, ?array $ids): ?array
    {
        if ($ids === null) {
            return null;
        }

        return array_values(array_filter(array_map(static fn (mixed $id): ?int => $context->central->id('country', (int) $id), $ids)));
    }
}
