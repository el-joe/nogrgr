<?php

namespace App\Modules\Ai\Services\PriceFinder;

use App\Modules\Ai\DTOs\AiQuotaStatus;
use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Quota\AiQuotaService;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\OpenAiCompatibleClient;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Localization\Models\Country;
use App\Shared\Money\Money;

/**
 * VEN-010b AI price finder: market prices of similar products in one selling country,
 * found by OpenAI with the built-in `web_search` tool (OpenAI only, decision #43), and a
 * suggested selling price (the median). A SUGGESTION ONLY: nothing is saved or applied —
 * the vendor's prices still go through the price list / product form and
 * Commerce\Pricing. Metered by the plan's monthly `ai_calls`.
 */
class PriceFinderService
{
    public const MAX_HITS = 8;

    /** Sanity band of a listing price (USD minor units). */
    private const MIN_MINOR = 50;

    private const MAX_MINOR = 10_000_000;

    public function __construct(
        private readonly OpenAiTools $tools,
        private readonly AiQuotaService $quota,
        private readonly StoreCatalogReader $reader,
    ) {}

    /**
     * @return array{query: string, country: array{id: int, name: string, iso2: string}, hits: list<array{source: string, title: string, url: string, price: int, price_label: string}>, average: int|null, median: int|null, min: int|null, max: int|null, suggested: int|null, labels: array<string, string|null>, sample_size: int}
     *
     * @throws AiQuotaExceededException|AiRequestException
     */
    public function find(StoreProduct $product, Country $country, int $tenantId): array
    {
        $view = $this->reader->view($product);
        $query = trim($view->name.($view->sku ? ' '.$view->sku : ''));

        $hits = $this->tools->run(
            new AiUsageContext(AiFeature::PriceFinder, $tenantId, 'store_product:'.$product->id),
            fn (OpenAiCompatibleClient $client): array => self::parse($client->respond(self::prompt($view->name, $view->summary, $country), [
                'tools' => [['type' => 'web_search', 'user_location' => ['type' => 'approximate', 'country' => $country->iso2]]],
            ])),
        );

        return self::summarise($query, $country, $hits);
    }

    public function quota(int $tenantId): AiQuotaStatus
    {
        return $this->quota->status(PlanLimit::AiCalls, $tenantId);
    }

    private static function prompt(string $name, ?string $summary, Country $country): string
    {
        return 'Find up to '.self::MAX_HITS.' current online store listings of this product (or the closest equivalent) sold to shoppers in '
            .$country->getTranslation('name', 'en').' ('.$country->iso2.'): "'.$name.'"'.($summary ? ' — '.mb_substr(strip_tags($summary), 0, 200) : '')."\n"
            .'Use web search. Convert every price to US dollars at today\'s rate. Reply with ONLY a JSON array, no prose: '
            .'[{"source": "store name or domain", "title": "listing title", "price_usd": 12.99, "url": "https://…"}]. Reply [] if nothing reliable is found.';
    }

    /**
     * @return list<array{source: string, title: string, url: string, price: int}>
     */
    public static function parse(string $text): array
    {
        if (preg_match('/\[.*\]/s', $text, $match) !== 1) {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'No JSON array.');
        }

        $rows = json_decode($match[0], true);
        $hits = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $price = is_numeric(str_replace([',', '$'], '', (string) ($row['price_usd'] ?? $row['price'] ?? ''))) ? (float) str_replace([',', '$'], '', (string) ($row['price_usd'] ?? $row['price'])) : null;
            $url = (string) ($row['url'] ?? '');
            $minor = $price !== null ? (int) round($price * 100) : 0;

            if ($minor < self::MIN_MINOR || $minor > self::MAX_MINOR || ! str_starts_with($url, 'https://')) {
                continue;
            }

            $hits[] = [
                'source' => mb_substr(trim((string) ($row['source'] ?? '')) ?: (string) parse_url($url, PHP_URL_HOST), 0, 80),
                'title' => mb_substr(trim((string) ($row['title'] ?? '')), 0, 160),
                'url' => mb_substr($url, 0, 500),
                'price' => $minor,
            ];
        }

        return array_slice($hits, 0, self::MAX_HITS);
    }

    /**
     * Market statistics of the hits (USD minor units); suggested = median.
     *
     * @param  list<array{source: string, title: string, url: string, price: int}>  $hits
     * @return array{query: string, country: array{id: int, name: string, iso2: string}, hits: list<array{source: string, title: string, url: string, price: int, price_label: string}>, average: int|null, median: int|null, min: int|null, max: int|null, suggested: int|null, labels: array<string, string|null>, sample_size: int}
     */
    public static function summarise(string $query, Country $country, array $hits): array
    {
        $prices = array_column($hits, 'price');
        sort($prices);
        $count = count($prices);
        $median = $count === 0 ? null : ($count % 2 === 1 ? $prices[intdiv($count, 2)] : intdiv($prices[$count / 2 - 1] + $prices[$count / 2], 2));
        $stats = [
            'average' => $count === 0 ? null : (int) round(array_sum($prices) / $count),
            'median' => $median,
            'min' => $prices[0] ?? null,
            'max' => $count === 0 ? null : $prices[$count - 1],
            'suggested' => $median,
        ];
        $label = static fn (?int $minor): ?string => $minor === null ? null : Money::ofMinor($minor)->format();

        return [
            'query' => $query,
            'country' => ['id' => $country->id, 'name' => (string) $country->name, 'iso2' => $country->iso2],
            'hits' => array_map(static fn (array $hit): array => $hit + ['price_label' => (string) $label($hit['price'])], $hits),
            ...$stats,
            'labels' => array_map($label, $stats),
            'sample_size' => $count,
        ];
    }
}
