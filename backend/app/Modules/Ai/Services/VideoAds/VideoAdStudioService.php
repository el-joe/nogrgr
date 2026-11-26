<?php

namespace App\Modules\Ai\Services\VideoAds;

use App\Modules\Ai\DTOs\AiQuotaStatus;
use App\Modules\Ai\DTOs\VideoAdRequestData;
use App\Modules\Ai\Enums\VideoAdAspect;
use App\Modules\Ai\Enums\VideoAdOutput;
use App\Modules\Ai\Enums\VideoAdPlatform;
use App\Modules\Ai\Enums\VideoAdPresenter;
use App\Modules\Ai\Enums\VideoAdStatus;
use App\Modules\Ai\Enums\VideoAdTone;
use App\Modules\Ai\Enums\VideoAdType;
use App\Modules\Ai\Enums\VideoAdVoice;
use App\Modules\Ai\Events\VideoAdUpdated;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Jobs\GenerateVideoAd;
use App\Modules\Ai\Models\VideoAd;
use App\Modules\Ai\Repositories\Contracts\VideoAdRepositoryContract;
use App\Modules\Ai\Services\Product\ProductAiTranslationService;
use App\Modules\Ai\Services\Quota\AiQuotaService;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\VideoAdLive;
use App\Modules\Billing\Enums\PlanLimit;
use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Localization\Models\Country;
use App\Modules\Localization\Services\StoreCountries;
use App\Modules\Platform\Services\StoreSettingsService;
use App\Shared\Services\BaseService;
use DomainException;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * AI Advertising Studio (VEN-010c / VEN-009e): queues video ads for the store's products
 * (quota first: this month's `ai_ads` usage + ads still being generated), lists, retries
 * and deletes them, and shapes everything the wizard, the Advertising Library and the
 * dashboard widget show. Generation itself: VideoAdGenerator (queued GenerateVideoAd).
 */
class VideoAdStudioService extends BaseService
{
    public const DURATIONS = [15, 30];

    public function __construct(
        private readonly VideoAdRepositoryContract $ads,
        private readonly StoreProductRepositoryContract $products,
        private readonly StoreCatalogReader $reader,
        private readonly AiQuotaService $quota,
        private readonly ProductAiTranslationService $languages,
        private readonly StoreCountries $countries,
        private readonly VideoAdRenderer $renderer,
    ) {}

    public function quota(int $tenantId): AiQuotaStatus
    {
        return $this->quota->status(PlanLimit::AiAds, $tenantId);
    }

    /**
     * @throws AiQuotaExceededException|DomainException
     */
    public function create(VideoAdRequestData $data, ?int $staffId, int $tenantId): VideoAd
    {
        if (! OpenAiTools::configured()) {
            throw new DomainException((string) __('ai::ad_studio.errors.not_configured'));
        }

        $this->ensureQuota($tenantId);
        $product = $this->product($data->productId);

        /** @var VideoAd $ad */
        $ad = $this->ads->create([
            'store_product_id' => $product->id,
            'product_name' => mb_substr($this->reader->view($product)->name, 0, 255),
            'created_by' => $staffId,
            'video_type' => $data->type,
            'presenter' => $data->presenter,
            'voice' => $data->voice,
            'tone' => $data->tone,
            'locale' => $data->locale,
            'platform' => $data->platform,
            'aspect' => $data->aspect,
            'duration' => $data->duration,
            'country_id' => $data->countryId,
            'notes' => $data->notes,
            'status' => VideoAdStatus::Queued,
            'progress' => 0,
        ]);

        $this->queue($ad, $tenantId);

        return $ad;
    }

    /**
     * Run a failed ad again with the same choices.
     *
     * @throws AiQuotaExceededException|DomainException
     */
    public function retry(VideoAd $ad, int $tenantId): VideoAd
    {
        if ($ad->status !== VideoAdStatus::Failed) {
            throw new DomainException((string) __('ai::ad_studio.errors.not_failed'));
        }

        if ($ad->store_product_id === null || $this->products->find($ad->store_product_id) === null) {
            throw new DomainException((string) __('ai::ad_studio.errors.product_missing'));
        }

        $this->ensureQuota($tenantId);
        $this->ads->update($ad, ['status' => VideoAdStatus::Queued, 'progress' => 0, 'error' => null, 'started_at' => null, 'finished_at' => null]);
        $this->queue($ad, $tenantId);

        return $ad;
    }

    public function delete(VideoAd $ad): void
    {
        Storage::disk('public')->deleteDirectory($ad->directory());
        $this->ads->delete($ad);
    }

    public function find(int $id): VideoAd
    {
        /** @var VideoAd */
        return $this->ads->findOrFail($id);
    }

    /**
     * Select2 page of the store's products with their image (studio product picker).
     *
     * @return array{results: list<array{id: int, text: string, image: string|null}>, pagination: array{more: bool}}
     */
    public function searchProducts(?string $term, int $page): array
    {
        $query = $this->products->query()->orderBy('sort_order')->orderBy('id');

        if ($term !== null && trim($term) !== '') {
            $this->products->whereMatches($query, trim($term));
        }

        $results = $this->reader->withRelations($query)->paginate(12, ['*'], 'page', max(1, $page));

        return [
            'results' => array_map(static fn (StoreProductView $view): array => ['id' => $view->id(), 'text' => $view->name, 'image' => $view->primaryImageUrl()], $this->reader->views($results->getCollection())),
            'pagination' => ['more' => $results->hasMorePages()],
        ];
    }

    /**
     * @return array{id: int, name: string, image: string|null}|null
     */
    public function productCard(?int $id): ?array
    {
        $product = $id !== null ? $this->products->find($id) : null;

        if (! $product instanceof StoreProduct) {
            return null;
        }

        $view = $this->reader->view($product);

        return ['id' => $product->id, 'name' => $view->name, 'image' => $view->primaryImageUrl()];
    }

    /**
     * Everything the wizard needs (JSON island of the studio modal).
     *
     * @return array<string, mixed>
     */
    public function studio(int $tenantId): array
    {
        $languages = $this->languages->storeLanguages();

        return [
            'platforms' => array_map(static fn (VideoAdPlatform $platform): array => [
                'value' => $platform->value,
                'aspects' => array_map(static fn (VideoAdAspect $aspect): string => $aspect->value, $platform->aspects()),
            ], VideoAdPlatform::cases()),
            'locale' => array_key_exists(app()->getLocale(), $languages) ? app()->getLocale() : (array_key_first($languages) ?? app()->getLocale()),
            'quota' => $this->quota($tenantId)->toClient(),
            'live' => VideoAdLive::config($tenantId),
            'renderer' => $this->renderer->available() ? VideoAdOutput::Video->value : VideoAdOutput::Slideshow->value,
            'urls' => [
                'store' => vendor_route('marketing.ads.store'),
                'products' => vendor_route('marketing.ads.products'),
                'show' => vendor_route('marketing.ads.show', ['ad' => '__ID__']),
                'library' => vendor_route('marketing.ads.index'),
            ],
        ];
    }

    /**
     * @return array{types: array<string, string>, presenters: array<string, string>, voices: array<string, string>, tones: array<string, string>, platforms: array<string, string>, aspects: array<string, string>, durations: array<int, string>, languages: array<string, string>, countries: array<int, string>}
     */
    public function options(): array
    {
        return [
            'types' => VideoAdType::options(),
            'presenters' => VideoAdPresenter::options(),
            'voices' => VideoAdVoice::options(),
            'tones' => VideoAdTone::options(),
            'platforms' => VideoAdPlatform::options(),
            'aspects' => VideoAdAspect::options(),
            'durations' => array_combine(self::DURATIONS, array_map(static fn (int $seconds): string => (string) __('ai::ad_studio.seconds', ['count' => $seconds]), self::DURATIONS)),
            'languages' => $this->languages->storeLanguages(),
            'countries' => $this->countries->available()->mapWithKeys(static fn (Country $country): array => [(int) $country->id => (string) $country->name])->all(),
        ];
    }

    /**
     * Client payload of one ad (status polling, result screen, library preview).
     *
     * @return array<string, mixed>
     */
    public function present(VideoAd $ad, bool $canManage): array
    {
        $disk = Storage::disk('public');
        $url = static fn (?string $path): ?string => $path !== null && $path !== '' ? $disk->url($path) : null;
        $country = $ad->country_id !== null ? $this->countries->find($ad->country_id) : null;
        $hashtags = $ad->hashtags ?? [];
        $text = trim((string) $ad->caption.($hashtags !== [] ? "\n\n".implode(' ', $hashtags) : ''));
        $videoUrl = $url($ad->video_path);

        return [
            'id' => $ad->id,
            'status' => $ad->status->value,
            'status_label' => $ad->status->label(),
            'status_tone' => $ad->status->tone(),
            'active' => $ad->status->isActive(),
            'progress' => $ad->progress,
            'product' => $ad->product_name,
            'product_id' => $ad->store_product_id,
            'type' => $ad->video_type->label(),
            'presenter' => $ad->presenter->label(),
            'voice' => $ad->voice->label(),
            'tone' => $ad->tone->label(),
            'language' => $this->languages->storeLanguages()[$ad->locale] ?? strtoupper($ad->locale),
            'platform' => $ad->platform->label(),
            'platform_icon' => $ad->platform->icon(),
            'aspect' => $ad->aspect->value,
            'aspect_class' => $ad->aspect->cssClass(),
            'duration' => $ad->duration,
            'duration_label' => (string) __('ai::ad_studio.seconds_short', ['count' => $ad->duration]),
            'country' => $country !== null ? ['name' => (string) $country->name, 'flag' => (string) $country->flag] : null,
            'headline' => $ad->headline,
            'hook' => $ad->hook,
            'captions' => array_values(array_map(static fn (array $scene): string => (string) ($scene['caption'] ?? ''), $ad->scenes ?? [])),
            'caption' => $ad->caption,
            'hashtags' => $hashtags,
            'text' => $text,
            'output' => $ad->output?->value,
            'video_url' => $videoUrl,
            'poster_url' => $url($ad->poster_path),
            'audio_url' => $url($ad->audio_path),
            'frames' => array_values(array_filter(array_map($url, $ad->frames ?? []))),
            'error' => $ad->error,
            'created_at' => $ad->created_at->translatedFormat('j M Y, H:i'),
            'share_text' => trim((string) $ad->headline.' '.($videoUrl ?? '')),
            'urls' => [
                'show' => vendor_route('marketing.ads.show', ['ad' => $ad->id]),
                'download' => $ad->isReady() ? vendor_route('marketing.ads.download', ['ad' => $ad->id]) : null,
                'retry' => $canManage && $ad->status === VideoAdStatus::Failed ? vendor_route('marketing.ads.retry', ['ad' => $ad->id]) : null,
                'destroy' => $canManage && ! $ad->status->isActive() ? vendor_route('marketing.ads.destroy', ['ad' => $ad->id]) : null,
                'store' => $ad->store_product_id !== null && tenant() !== null ? StoreSettingsService::storeUrl(tenant()).'/products/'.$ad->store_product_id : null,
            ],
        ];
    }

    /**
     * Newest ready ads for the dashboard widget (VEN-009e).
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit, bool $canManage): array
    {
        return $this->ads->latestReady($limit)->map(fn (VideoAd $ad): array => $this->present($ad, $canManage))->values()->all();
    }

    public function activeCount(): int
    {
        return $this->ads->activeCount();
    }

    /**
     * Downloadable file of a ready ad: the MP4, or a ZIP of the slideshow (scenes, voice-over, captions).
     *
     * @return array{path: string, name: string, temporary: bool}
     */
    public function download(VideoAd $ad): array
    {
        if (! $ad->isReady()) {
            throw new DomainException((string) __('ai::ad_studio.errors.not_ready'));
        }

        $disk = Storage::disk('public');
        $base = 'ad-'.$ad->id;

        if ($ad->output === VideoAdOutput::Video && $ad->video_path !== null && $disk->exists($ad->video_path)) {
            return ['path' => $disk->path($ad->video_path), 'name' => $base.'.mp4', 'temporary' => false];
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'ad-zip');
        $zip = new ZipArchive;

        if ($zipPath === false || $zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new DomainException((string) __('ai::ad_studio.errors.generic'));
        }

        foreach (array_values($ad->frames ?? []) as $index => $frame) {
            if ($disk->exists($frame)) {
                $zip->addFile($disk->path($frame), sprintf('scene-%d.%s', $index + 1, pathinfo($frame, PATHINFO_EXTENSION)));
            }
        }

        if ($ad->audio_path !== null && $disk->exists($ad->audio_path)) {
            $zip->addFile($disk->path($ad->audio_path), 'voice-over.mp3');
        }

        $captions = array_map(static fn (array $scene): string => (string) ($scene['caption'] ?? ''), $ad->scenes ?? []);
        $zip->addFromString('script.txt', implode("\n\n", array_filter([(string) $ad->headline, implode("\n", $captions), (string) $ad->voiceover, (string) $ad->caption, implode(' ', $ad->hashtags ?? [])])));
        $zip->close();

        return ['path' => $zipPath, 'name' => $base.'.zip', 'temporary' => true];
    }

    /**
     * @throws AiQuotaExceededException
     */
    private function ensureQuota(int $tenantId): void
    {
        $status = $this->quota($tenantId);

        if ($status->max !== null && $status->used + $this->ads->activeCount() >= $status->max) {
            throw new AiQuotaExceededException($status);
        }
    }

    private function product(int $id): StoreProduct
    {
        $product = $this->products->find($id);

        if (! $product instanceof StoreProduct) {
            throw new DomainException((string) __('ai::ad_studio.errors.product_missing'));
        }

        return $product;
    }

    private function queue(VideoAd $ad, int $tenantId): void
    {
        GenerateVideoAd::dispatch($ad->id)->afterCommit();

        try {
            VideoAdUpdated::dispatch($tenantId, $ad);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
