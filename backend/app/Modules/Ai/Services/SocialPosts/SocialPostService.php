<?php

namespace App\Modules\Ai\Services\SocialPosts;

use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\SocialPlatform;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\OpenAiCompatibleClient;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;

/**
 * VEN-010c AI social posts: a caption + hashtags for one store product, per platform
 * and language (OpenAI only, decision #43). Nothing is stored or posted — the vendor
 * copies the text and downloads the product image. Metered by the plan's `ai_calls`.
 */
class SocialPostService
{
    public function __construct(
        private readonly OpenAiTools $tools,
        private readonly StoreCatalogReader $reader,
    ) {}

    /**
     * @return array{platform: string, locale: string, caption: string, hashtags: list<string>, text: string, link: string, image: string|null}
     *
     * @throws AiQuotaExceededException|AiRequestException
     */
    public function generate(StoreProduct $product, SocialPlatform $platform, string $locale, string $languageName, string $link, string $storeName, ?string $price): array
    {
        $view = $this->reader->view($product);

        $answer = $this->tools->run(
            new AiUsageContext(AiFeature::SocialPost, (int) tenant()?->getTenantKey(), 'store_product:'.$product->id),
            fn (OpenAiCompatibleClient $client): array => OpenAiTools::json($client, [
                ['role' => 'system', 'content' => 'You write social media posts for an online store. Answer ONLY with a JSON object {"caption": string, "hashtags": [string]}. '
                    .'Write in '.$languageName.' ('.$locale.'). '.$platform->style().' Hashtags without spaces, at most '.$platform->maxHashtags().', no "#" duplicates. Never invent discounts, prices or facts that are not given.'],
                ['role' => 'user', 'content' => json_encode([
                    'store' => $storeName,
                    'product' => $view->name,
                    'summary' => $view->summary !== null ? mb_substr(strip_tags($view->summary), 0, 400) : null,
                    'description' => $view->description !== null ? mb_substr(strip_tags($view->description), 0, 800) : null,
                    'price' => $price,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ], 0.8),
        );

        $caption = trim((string) ($answer['caption'] ?? ''));

        if ($caption === '') {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Caption missing.');
        }

        $hashtags = collect(is_array($answer['hashtags'] ?? null) ? $answer['hashtags'] : [])
            ->map(static fn (mixed $tag): string => '#'.ltrim(preg_replace('/\s+/u', '', (string) $tag) ?? '', '#'))
            ->filter(static fn (string $tag): bool => mb_strlen($tag) > 1)
            ->unique()
            ->take($platform->maxHashtags())
            ->values()
            ->all();

        $text = self::compose($caption, $hashtags, $link, $platform->maxCaption());

        return [
            'platform' => $platform->value,
            'locale' => $locale,
            'caption' => $caption,
            'hashtags' => $hashtags,
            'text' => $text,
            'link' => $link,
            'image' => $view->primaryImageUrl(),
        ];
    }

    /**
     * Caption + link + hashtags trimmed to the platform limit (hashtags dropped first,
     * then the caption shortened).
     *
     * @param  list<string>  $hashtags
     */
    public static function compose(string $caption, array $hashtags, string $link, int $max): string
    {
        $tags = $hashtags;

        while (true) {
            $text = trim($caption."\n\n".$link.($tags !== [] ? "\n".implode(' ', $tags) : ''));

            if (mb_strlen($text) <= $max || $tags === []) {
                break;
            }

            array_pop($tags);
        }

        if (mb_strlen($text) > $max) {
            $room = max(1, $max - mb_strlen($link) - 3);
            $text = rtrim(mb_substr($caption, 0, $room - 1)).'…'."\n\n".$link;
        }

        return $text;
    }
}
