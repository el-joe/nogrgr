<?php

namespace App\Modules\Catalog\Imports\Drivers;

use App\Modules\Catalog\DTOs\Imports\ExternalCatalogPage;
use App\Modules\Catalog\DTOs\Imports\ExternalCategoryData;
use App\Modules\Catalog\DTOs\Imports\ExternalImageData;
use App\Modules\Catalog\DTOs\Imports\ExternalOptionData;
use App\Modules\Catalog\DTOs\Imports\ExternalProductData;
use App\Modules\Catalog\DTOs\Imports\ExternalVariantData;
use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Imports\Contracts\ExternalCatalogDriver;
use App\Modules\Catalog\Imports\Exceptions\ExternalCatalogException;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Integrations\Services\IntegrationSettingsService;
use App\Shared\Money\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Neozena public catalog API (no token needed; base URL override and optional token in
 * Admin › Integrations › Neozena):
 *   GET {base_url}/products?page=N  → {data: [...], meta: {last_page, total}}
 *   GET {base_url}/products/{id}    → {data: {...}} (used when a list item is a summary)
 *   GET {base_url}/categories/{id}  → {data: {...}} (creating a missing category)
 * Prices are USD decimals; translations arrive as [{locale, title|name, ...}].
 */
class NeozenaDriver implements ExternalCatalogDriver
{
    public const DEFAULT_BASE_URL = 'https://neozena.com/api/catalog';

    public const TIMEOUT_SECONDS = 60;

    public const RETRIES = 2;

    /**
     * Safety cap of the GET /variants pages read for unknown options.
     */
    public const VARIANT_PAGES_MAX = 50;

    /**
     * @var array<int, array{attribute_id: int, attribute_name: array<string, string>, name: array<string, string>, swatch: string|null}>|null
     */
    private ?array $variantCatalog = null;

    public function __construct(private readonly IntegrationSettingsService $settings) {}

    public function driver(): ImportDriver
    {
        return ImportDriver::Neozena;
    }

    public function isConfigured(): bool
    {
        return filled($this->settings->values(IntegrationSection::Neozena)['base_url'] ?? null);
    }

    public function endpoint(): ?string
    {
        $host = parse_url($this->baseUrl(), PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    public function fetchPage(int $page): ExternalCatalogPage
    {
        if (! $this->isConfigured()) {
            throw ExternalCatalogException::notConfigured();
        }

        $payload = $this->get('/products', $page, ['page' => $page]);
        $rows = $payload['data'] ?? null;

        if (! is_array($rows)) {
            throw ExternalCatalogException::invalidResponse($page);
        }

        $items = [];
        $invalid = [];

        foreach ($rows as $row) {
            $row = is_array($row) ? $row : [];

            try {
                if (! isset($row['translations']) && ! isset($row['variant_combinations']) && is_numeric($row['id'] ?? null)) {
                    $detail = $this->get('/products/'.(int) $row['id'], $page)['data'] ?? null;
                    $row = is_array($detail) ? $detail : $row;
                }

                $items[] = $this->mapProduct($row);
            } catch (InvalidArgumentException|ExternalCatalogException $exception) {
                $invalid[] = [
                    'external_id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : null,
                    'sku' => is_scalar($row['sku'] ?? null) ? (string) $row['sku'] : null,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $info = $this->info($payload, $rows, $page);

        return new ExternalCatalogPage($page, $info['last_page'], $info['total'], $items, $invalid);
    }

    public function pageInfo(int $page): array
    {
        if (! $this->isConfigured()) {
            throw ExternalCatalogException::notConfigured();
        }

        $payload = $this->get('/products', $page, ['page' => $page]);
        $rows = $payload['data'] ?? null;

        if (! is_array($rows)) {
            throw ExternalCatalogException::invalidResponse($page);
        }

        return $this->info($payload, $rows, $page);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<mixed>  $rows
     * @return array{last_page: int, total: int, count: int}
     */
    private function info(array $payload, array $rows, int $page): array
    {
        // A page past the end keeps the source's real last page (the CLI --from-page check).
        $reported = $payload['meta']['last_page'] ?? $payload['last_page'] ?? null;
        $lastPage = is_numeric($reported) && ($rows === [] || (int) $reported >= $page) ? max(1, (int) $reported) : $page;
        $total = (int) ($payload['meta']['total'] ?? $payload['total'] ?? count($rows));

        return ['last_page' => $lastPage, 'total' => max($total, count($rows)), 'count' => count($rows)];
    }

    public function fetchCategory(int $externalId): ?ExternalCategoryData
    {
        if (! $this->isConfigured()) {
            throw ExternalCatalogException::notConfigured();
        }

        try {
            $payload = $this->get('/categories/'.$externalId, 1);
        } catch (ExternalCatalogException) {
            return null;
        }

        $row = $payload['data'] ?? $payload;

        return is_array($row) && is_numeric($row['id'] ?? null) ? ($this->categories([$row])[0] ?? null) : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, int $page, array $query = []): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().$path, $query);
        } catch (ConnectionException $exception) {
            throw ExternalCatalogException::unreachable($exception->getMessage());
        }

        if (! $response->successful()) {
            throw ExternalCatalogException::http($response->status(), $page);
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw ExternalCatalogException::invalidResponse($page);
        }

        return $json;
    }

    private function client(): PendingRequest
    {
        $token = $this->settings->values(IntegrationSection::Neozena)['api_token'] ?? null;

        return Http::acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(self::RETRIES, 500, throw: false)
            ->when(is_string($token) && $token !== '', static fn (PendingRequest $request) => $request->withToken((string) $token));
    }

    private function baseUrl(): string
    {
        $url = $this->settings->values(IntegrationSection::Neozena)['base_url'] ?? null;

        return rtrim(is_string($url) && $url !== '' ? $url : self::DEFAULT_BASE_URL, '/');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mapProduct(array $row): ExternalProductData
    {
        if (! is_numeric($row['id'] ?? null) || (int) $row['id'] <= 0) {
            throw new InvalidArgumentException(__('catalog::imports.errors.missing_id'));
        }

        $id = (int) $row['id'];
        $translations = $this->translationRows($row['translations'] ?? []);
        $name = $this->localized($translations, 'title', $row['title'] ?? $row['name'] ?? null);

        if ($name === []) {
            throw new InvalidArgumentException(__('catalog::imports.errors.missing_name'));
        }

        $groups = $this->optionGroups($row['variants'] ?? []);
        $variants = [];

        foreach (is_array($row['variant_combinations'] ?? null) ? $row['variant_combinations'] : [] as $combination) {
            if (is_array($combination)) {
                $variants[] = $this->mapVariant($combination, $groups);
            }
        }

        $price = $this->money($row['current_price'] ?? null);

        if ($price === null || $price === 0) {
            $price = $variants[0]->priceMinor ?? $price;
        }

        if ($price === null) {
            throw new InvalidArgumentException(__('catalog::imports.errors.missing_price'));
        }

        $sku = is_scalar($row['sku'] ?? null) && trim((string) $row['sku']) !== '' ? trim((string) $row['sku']) : 'NZ-'.$id;

        return new ExternalProductData(
            externalId: $id,
            sku: mb_substr($sku, 0, 191),
            name: $name,
            priceMinor: $price,
            isActive: (bool) ($row['status'] ?? false),
            summary: $this->localized($translations, 'summary'),
            description: $this->localized($translations, 'description'),
            metaDescription: $this->localized($translations, 'meta_description'),
            metaKeywords: $this->keywords($translations),
            weightGrams: is_numeric($row['weight'] ?? null) ? max(0, (int) round((float) $row['weight'])) : null,
            isTaxable: (bool) ($row['taxable'] ?? false),
            requiresShipping: (bool) ($row['shippable'] ?? true),
            unlimitedStock: (bool) ($row['stock_unlimited'] ?? false),
            stock: is_numeric($row['stock'] ?? null) ? (int) $row['stock'] : null,
            images: $this->images($row['images'] ?? []),
            variants: $variants,
            categories: $this->categories($row['categories'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $combination
     * @param  array<int, array{attribute_id: int, attribute_name: array<string, string>, name: array<string, string>}>  $groups
     */
    private function mapVariant(array $combination, array $groups): ExternalVariantData
    {
        if (! is_numeric($combination['id'] ?? null)) {
            throw new InvalidArgumentException(__('catalog::imports.errors.variant_missing_id'));
        }

        $inline = [];

        foreach (is_array($combination['options'] ?? null) ? $combination['options'] : [] as $option) {
            if (is_array($option) && is_numeric($option['id'] ?? null)) {
                $inline[(int) $option['id']] = $option;
            }
        }

        $optionIds = is_array($combination['option_ids'] ?? null) ? $combination['option_ids'] : array_keys($inline);
        $options = [];

        foreach (array_unique(array_map('intval', array_filter($optionIds, 'is_numeric'))) as $optionId) {
            $known = $groups[$optionId] ?? null;
            $raw = $inline[$optionId] ?? [];

            if ($known === null && is_numeric($raw['variant_id'] ?? null)) {
                $known = [
                    'attribute_id' => (int) $raw['variant_id'],
                    'attribute_name' => $this->localized($this->translationRows($raw['variant_translations'] ?? []), 'name', $raw['variant_name'] ?? null),
                    'name' => $this->localized($this->translationRows($raw['translations'] ?? []), 'name', $raw['value'] ?? null),
                    'swatch' => $this->swatch($raw),
                ];
            }

            // Not described by the product: the source's attribute list (GET /variants).
            $known ??= $this->variantCatalog()[$optionId] ?? null;

            if ($known === null || $known['name'] === [] || $known['attribute_name'] === []) {
                throw new InvalidArgumentException(__('catalog::imports.errors.unknown_option', ['id' => $optionId]));
            }

            $options[] = new ExternalOptionData($known['attribute_id'], $known['attribute_name'], $optionId, $known['name'], $known['swatch'] ?? null);
        }

        $sku = is_scalar($combination['sku'] ?? null) && trim((string) $combination['sku']) !== '' ? mb_substr(trim((string) $combination['sku']), 0, 191) : null;

        return new ExternalVariantData(
            externalId: (int) $combination['id'],
            sku: $sku,
            priceMinor: $this->money($combination['price'] ?? null),
            stock: is_numeric($combination['stock'] ?? null) ? (int) $combination['stock'] : 0,
            weightGrams: is_numeric($combination['weight'] ?? null) && (float) $combination['weight'] > 0 ? (int) round((float) $combination['weight']) : null,
            isActive: (bool) ($combination['is_active'] ?? true),
            options: $options,
            imageUrl: is_string($combination['thumbnail_path'] ?? null) && filter_var($combination['thumbnail_path'], FILTER_VALIDATE_URL) !== false ? $combination['thumbnail_path'] : null,
        );
    }

    /**
     * Option id => its attribute and names, from the product's variant groups.
     *
     * @return array<int, array{attribute_id: int, attribute_name: array<string, string>, name: array<string, string>, swatch: string|null}>
     */
    private function optionGroups(mixed $groups): array
    {
        $map = [];

        foreach (is_array($groups) ? $groups : [] as $group) {
            if (! is_array($group) || ! is_numeric($group['id'] ?? null)) {
                continue;
            }

            $attributeName = $this->localized($this->translationRows($group['translations'] ?? []), 'name', $group['name'] ?? null);

            foreach (is_array($group['options'] ?? null) ? $group['options'] : [] as $option) {
                if (is_array($option) && is_numeric($option['id'] ?? null)) {
                    $map[(int) $option['id']] = [
                        'attribute_id' => (int) $group['id'],
                        'attribute_name' => $attributeName,
                        'name' => $this->localized($this->translationRows($option['translations'] ?? []), 'name', $option['value'] ?? $option['name'] ?? null),
                        'swatch' => $this->swatch($option),
                    ];
                }
            }
        }

        return $map;
    }

    /**
     * Colour code of an option (`color`, `color_code`, `hex`, `swatch` or `code` as #hex).
     *
     * @param  array<string, mixed>  $option
     */
    private function swatch(array $option): ?string
    {
        foreach (['color', 'color_code', 'hex', 'swatch', 'code'] as $key) {
            $value = $option[$key] ?? null;

            if (is_string($value) && preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($value)) === 1) {
                return '#'.strtolower(ltrim(trim($value), '#'));
            }
        }

        return null;
    }

    /**
     * Every option of the source's attribute list (GET /variants, all pages), read once
     * per process and only when a product names an option it does not describe.
     *
     * @return array<int, array{attribute_id: int, attribute_name: array<string, string>, name: array<string, string>, swatch: string|null}>
     */
    private function variantCatalog(): array
    {
        if ($this->variantCatalog !== null) {
            return $this->variantCatalog;
        }

        $this->variantCatalog = [];

        for ($page = 1; $page <= self::VARIANT_PAGES_MAX; $page++) {
            try {
                $payload = $this->get('/variants', $page, ['page' => $page]);
            } catch (ExternalCatalogException) {
                break;
            }

            $rows = $payload['data'] ?? null;

            if (! is_array($rows) || $rows === []) {
                break;
            }

            $this->variantCatalog += $this->optionGroups($rows);
            $last = $payload['meta']['last_page'] ?? $payload['last_page'] ?? 1;

            if (! is_numeric($last) || $page >= (int) $last) {
                break;
            }
        }

        return $this->variantCatalog;
    }

    /**
     * @return list<ExternalImageData>
     */
    private function images(mixed $images): array
    {
        $result = [];

        foreach (is_array($images) ? $images : [] as $image) {
            if (! is_array($image) || ($image['type'] ?? 'image') === 'video' || ! is_numeric($image['id'] ?? null)) {
                continue;
            }

            $url = $image['image_path'] ?? $image['url'] ?? null;

            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                $result[] = new ExternalImageData((int) $image['id'], $url, (bool) ($image['is_primary'] ?? false));
            }
        }

        usort($result, static fn (ExternalImageData $a, ExternalImageData $b): int => (int) $b->isPrimary <=> (int) $a->isPrimary);

        return $result;
    }

    /**
     * @return list<ExternalCategoryData>
     */
    private function categories(mixed $categories): array
    {
        $result = [];

        foreach (is_array($categories) ? $categories : [] as $category) {
            if (! is_array($category) || ! is_numeric($category['id'] ?? null)) {
                continue;
            }

            $translations = $this->translationRows($category['translations'] ?? []);
            $name = $this->localized($translations, 'name', $category['name'] ?? null);
            $slug = $translations['en']['slug'] ?? $category['slug'] ?? null;
            $image = $category['thumbnail_path'] ?? $category['image_path'] ?? $category['banner_path'] ?? null;
            $result[] = new ExternalCategoryData(
                (int) $category['id'],
                $name !== [] ? $name : ['en' => '#'.(int) $category['id']],
                is_numeric($category['parent_id'] ?? null) && (int) $category['parent_id'] > 0 ? (int) $category['parent_id'] : null,
                is_string($slug) && trim($slug) !== '' ? trim($slug) : null,
                $this->localized($translations, 'description'),
                is_string($image) && filter_var($image, FILTER_VALIDATE_URL) !== false ? $image : null,
            );
        }

        return $result;
    }

    /**
     * @return array<string, array<string, mixed>> locale => translation row
     */
    private function translationRows(mixed $rows): array
    {
        $result = [];

        foreach (is_array($rows) ? $rows : [] as $key => $row) {
            if (is_array($row)) {
                $locale = is_string($row['locale'] ?? null) ? $row['locale'] : (is_string($key) ? $key : null);

                if ($locale !== null && $locale !== '') {
                    $result[strtolower(substr($locale, 0, 5))] = $row;
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<string, string>
     */
    private function localized(array $translations, string $field, mixed $fallback = null): array
    {
        $result = [];

        foreach ($translations as $locale => $row) {
            $value = $row[$field] ?? ($field === 'title' ? ($row['name'] ?? null) : null);

            if (is_string($value) && trim($value) !== '') {
                $result[$locale] = trim($value);
            }
        }

        if ($result === [] && is_scalar($fallback) && trim((string) $fallback) !== '') {
            $result['en'] = trim((string) $fallback);
        }

        return $result;
    }

    /**
     * Keywords arrive as a comma list, a JSON list of {value} tags or an array of them.
     *
     * @param  array<string, array<string, mixed>>  $translations
     * @return array<string, string>
     */
    private function keywords(array $translations): array
    {
        $result = [];

        foreach ($translations as $locale => $row) {
            $raw = $row['meta_keywords'] ?? null;

            if (is_string($raw) && str_starts_with(trim($raw), '[')) {
                $raw = json_decode($raw, true);
            }

            if (is_array($raw)) {
                $raw = implode(',', array_filter(array_map(static fn (mixed $tag): ?string => is_array($tag) ? (is_string($tag['value'] ?? null) ? $tag['value'] : null) : (is_string($tag) ? $tag : null), $raw)));
            }

            if (is_string($raw) && trim($raw) !== '') {
                $result[$locale] = mb_substr(trim($raw), 0, 500);
            }
        }

        return $result;
    }

    private function money(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        if ((float) $value < 0) {
            throw new InvalidArgumentException(__('catalog::imports.errors.negative_price'));
        }

        return Money::of((string) $value)->minor;
    }
}
