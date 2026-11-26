<?php

namespace App\Modules\Ai\Http\Controllers\Storefront;

use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Http\Requests\Storefront\ImageSearchRequest;
use App\Modules\Ai\Services\ImageSearch\ImageSearchService;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\RemoteImageFetcher;
use App\Modules\Storefront\Services\Catalog\ProductCards;
use App\Modules\Storefront\Services\Catalog\ProductIndex;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Search by image: storefront modal (`storefront.search.image`, SF-ECO-012 / SF-ELO-013 /
 * SF-SQF-013) and the mobile store API (`store-api.v1.search.image`). Candidates = the
 * products the store currently sells in the shopper's country (storefront index).
 * Errors: 422 invalid photo / URL, 429 store quota reached, 503 provider problem.
 */
class ImageSearchController extends Controller
{
    public function __construct(
        private readonly ImageSearchService $search,
        private readonly ProductIndex $index,
        private readonly ProductCards $cards,
        private readonly RemoteImageFetcher $fetcher,
    ) {}

    public function __invoke(ImageSearchRequest $request): JsonResponse
    {
        if (! OpenAiTools::configured()) {
            return $this->error('unavailable', __st('search.image.errors.unavailable'), 503);
        }

        $image = $this->image($request);

        if ($image === null) {
            return $this->error('invalid_image', __st('search.image.errors.url'), 422);
        }

        $entries = $this->index->entries();

        try {
            $scores = $this->search->search($image['binary'], $image['mime'], array_keys($entries), (int) tenant()?->getTenantKey());
        } catch (AiQuotaExceededException) {
            return $this->error('quota_reached', __st('search.image.errors.quota'), 429);
        } catch (AiRequestException) {
            return $this->error('unavailable', __st('search.image.errors.unavailable'), 503);
        }

        $products = [];

        foreach ($scores as $id => $score) {
            $entry = $entries[$id] ?? null;

            if ($entry === null) {
                continue;
            }

            $card = $this->cards->card($entry);
            $products[] = [
                'id' => $card->id,
                'name' => $card->name,
                'url' => $card->url,
                'image' => $card->image,
                'price' => $card->price,
                'price_minor' => $card->priceMinor,
                'compare' => $card->compare,
                'in_stock' => $card->inStock,
                'match' => (int) round(max(0.0, min(1.0, $score)) * 100),
            ];
        }

        return new JsonResponse(['data' => [
            'total' => count($products),
            'products' => $products,
            'title' => $products === [] ? __st('search.image.empty_title') : __st('search.image.found', ['count' => count($products)]),
        ]]);
    }

    /**
     * @return array{binary: string, mime: string}|null
     */
    private function image(ImageSearchRequest $request): ?array
    {
        $file = $request->file('image');

        if ($file instanceof UploadedFile) {
            $binary = (string) file_get_contents($file->getRealPath());

            return $binary === '' ? null : ['binary' => $binary, 'mime' => (string) $file->getMimeType()];
        }

        return $this->fetcher->fetch((string) $request->validated('url'));
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'error' => $code], $status);
    }
}
