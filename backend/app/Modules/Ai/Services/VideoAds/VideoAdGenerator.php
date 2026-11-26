<?php

namespace App\Modules\Ai\Services\VideoAds;

use App\Modules\Ai\DTOs\AiUsageContext;
use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\VideoAdOutput;
use App\Modules\Ai\Enums\VideoAdStatus;
use App\Modules\Ai\Events\VideoAdUpdated;
use App\Modules\Ai\Exceptions\AiQuotaExceededException;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Models\VideoAd;
use App\Modules\Ai\Notifications\VideoAdNotification;
use App\Modules\Ai\Repositories\Contracts\VideoAdRepositoryContract;
use App\Modules\Ai\Services\Product\ProductAiTranslationService;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\OpenAiCompatibleClient;
use App\Modules\Catalog\DTOs\Vendor\StoreProductView;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\StoreProduct;
use App\Modules\Catalog\Repositories\Contracts\StoreProductRepositoryContract;
use App\Modules\Catalog\Services\Vendor\StoreCatalogReader;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Localization\Services\StoreCountries;
use App\Shared\Services\BaseService;
use finfo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Generates one AI Advertising Studio ad (VEN-010c, queued job, tenant context, OpenAI
 * only — decision #43). Inside ONE OpenAiTools::run() (= one `video_ad` usage row against
 * the plan's monthly `ai_ads`):
 *   1. script (chat, JSON): headline, hook, N scene captions + shot descriptions, voice-over, social caption + hashtags;
 *   2. scenes (OpenAI images): from the product photo as reference when it has one
 *      (images/edits), else text-to-image; the real product photo closes the ad;
 *   3. voice-over (OpenAI text-to-speech) unless "no voice" was chosen.
 * Then ffmpeg assembles the MP4 + poster (VideoAdRenderer); without ffmpeg the ad is
 * delivered as an animated slideshow (scene images + voice track). Every step updates the
 * row's status / progress and broadcasts VideoAdUpdated; the creator is notified at the end.
 */
class VideoAdGenerator extends BaseService
{
    public function __construct(
        private readonly OpenAiTools $tools,
        private readonly VideoAdRepositoryContract $ads,
        private readonly StoreProductRepositoryContract $products,
        private readonly StoreCatalogReader $reader,
        private readonly VideoAdRenderer $renderer,
        private readonly ProductAiTranslationService $languages,
        private readonly StoreCountries $countries,
        private readonly VendorStaffRepositoryContract $staff,
    ) {}

    public static function sceneCount(int $duration): int
    {
        return $duration >= 30 ? 6 : 4;
    }

    public function generate(int $adId): void
    {
        $ad = $this->ads->find($adId);

        if (! $ad instanceof VideoAd || ! $ad->status->isActive()) {
            return;
        }

        $product = $ad->store_product_id !== null ? $this->products->find($ad->store_product_id) : null;

        if (! $product instanceof StoreProduct) {
            $this->fail($ad, (string) __('ai::ad_studio.errors.product_missing'));

            return;
        }

        $tenantId = (int) tenant()?->getTenantKey();
        $disk = Storage::disk('public');
        $disk->deleteDirectory($ad->directory());
        $disk->makeDirectory($ad->directory());
        $this->advance($ad, VideoAdStatus::Scripting, 5, ['started_at' => now(), 'error' => null]);

        try {
            $view = $this->reader->view($product);
            $reference = $this->productImage($product);

            $assets = $this->tools->run(
                new AiUsageContext(AiFeature::VideoAd, $tenantId, 'video_ad:'.$ad->id),
                fn (OpenAiCompatibleClient $client): array => $this->create($client, $ad, $view, $reference, $disk),
                timeout: 180,
            );

            $this->assemble($ad, $assets, $disk);
        } catch (AiQuotaExceededException $exception) {
            $this->fail($ad, $exception->getMessage());
        } catch (AiRequestException $exception) {
            $this->fail($ad, (string) __('ai::ad_studio.errors.provider'));
            report($exception);
        } catch (Throwable $exception) {
            $this->fail($ad, (string) __('ai::ad_studio.errors.generic'));
            report($exception);
        }
    }

    /**
     * Script, scene images and voice-over (all OpenAI calls of one ad).
     *
     * @param  array{binary: string, mime: string, extension: string}|null  $reference
     * @return array{images: list<string>, captions: list<string>, audio: string|null}
     */
    private function create(OpenAiCompatibleClient $client, VideoAd $ad, StoreProductView $view, ?array $reference, Filesystem $disk): array
    {
        $count = self::sceneCount($ad->duration);
        $script = $this->script($client, $ad, $view, $count);
        $this->ads->update($ad, $script);
        $this->advance($ad, VideoAdStatus::Visuals, 15);

        $images = [];
        $captions = array_map(static fn (array $scene): string => $scene['caption'], $script['scenes']);
        $generated = $reference !== null ? $count - 1 : $count;

        foreach (array_slice($script['scenes'], 0, $generated) as $index => $scene) {
            $prompt = $this->scenePrompt($ad, $view->name, $scene['visual'], $reference !== null);
            $binary = $reference !== null
                ? $client->editImage($reference['binary'], $reference['mime'], $prompt, OpenAiTools::imageModel(), $ad->aspect->imageSize())
                : $client->images($prompt, OpenAiTools::imageModel(), 1, $ad->aspect->imageSize())[0];
            $path = $ad->directory().'/scene-'.($index + 1).'.png';
            $disk->put($path, $binary);
            $images[] = $path;
            $this->advance($ad, VideoAdStatus::Visuals, 15 + (int) round(55 * ($index + 1) / $count));
        }

        if ($reference !== null) {
            $path = $ad->directory().'/scene-'.$count.'.'.$reference['extension'];
            $disk->put($path, $reference['binary']);
            $images[] = $path;
        }

        $audio = null;
        $voice = $ad->voice->openAiVoice();

        if ($voice !== null && trim((string) $script['voiceover']) !== '') {
            $this->advance($ad, VideoAdStatus::Voice, 75);
            $model = OpenAiTools::ttsModel();
            $instructions = str_contains($model, '4o') ? 'Speak naturally in '.$this->languageName($ad->locale).', '.$ad->tone->prompt().', like a social media ad voice-over.' : null;
            $audio = $ad->directory().'/voice.mp3';
            $disk->put($audio, $client->speech((string) $script['voiceover'], $voice, $model, $instructions));
        }

        return ['images' => $images, 'captions' => $captions, 'audio' => $audio];
    }

    /**
     * @return array{headline: string, hook: string, scenes: list<array{caption: string, visual: string}>, voiceover: string, caption: string, hashtags: list<string>}
     */
    private function script(OpenAiCompatibleClient $client, VideoAd $ad, StoreProductView $view, int $count): array
    {
        $language = $this->languageName($ad->locale);
        $words = (int) floor(($ad->duration - 1) * 2.3);
        $country = $ad->country_id !== null ? $this->countries->find($ad->country_id)?->name : null;

        $answer = OpenAiTools::json($client, [
            ['role' => 'system', 'content' => 'You are a senior performance-marketing copywriter writing short vertical video ads for an online store. '
                .'Answer ONLY with a JSON object {"headline": string, "hook": string, "scenes": [{"caption": string, "visual": string}], "voiceover": string, "caption": string, "hashtags": [string]}. '
                .'Exactly '.$count.' scenes for a '.$ad->duration.'-second ad on '.$ad->platform->label().'. '
                .'Write headline, hook, scene captions, voiceover and caption in '.$language.' ('.$ad->locale.'); write every "visual" in English. '
                .'"hook" = the first line that stops the scroll (max 120 characters). Scene "caption" = short on-screen text, max 45 characters; the first scene shows the hook idea, the last scene is a call to action. '
                .'"visual" = one photographic shot description of the scene (camera, setting, action), never text in the image. '
                .'"voiceover" = spoken script of at most '.$words.' words. "caption" = the social post text for '.$ad->platform->label().' (max '.$ad->platform->maxCaption().' characters). '
                .'At most '.$ad->platform->maxHashtags().' hashtags without spaces. Creative direction: '.$ad->video_type->direction().' '.$ad->presenter->prompt().' Tone: '.$ad->tone->prompt().'. '
                .'Never invent prices, discounts, guarantees or facts that are not given.'],
            ['role' => 'user', 'content' => json_encode(array_filter([
                'store' => (string) (tenant()?->name ?? ''),
                'product' => $view->name,
                'summary' => $view->summary !== null ? mb_substr(strip_tags($view->summary), 0, 400) : null,
                'description' => $view->description !== null ? mb_substr(strip_tags($view->description), 0, 900) : null,
                'target_market' => $country,
                'notes' => $ad->notes,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ], 0.8);

        $scenes = [];

        foreach (is_array($answer['scenes'] ?? null) ? $answer['scenes'] : [] as $scene) {
            if (is_array($scene) && trim((string) ($scene['visual'] ?? '')) !== '') {
                $scenes[] = ['caption' => mb_substr(trim((string) ($scene['caption'] ?? '')), 0, 80), 'visual' => mb_substr(trim((string) $scene['visual']), 0, 600)];
            }
        }

        $headline = trim((string) ($answer['headline'] ?? ''));

        if ($headline === '' || count($scenes) < 2) {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Ad script incomplete.');
        }

        while (count($scenes) < $count) {
            $scenes[] = $scenes[count($scenes) - 1];
        }

        $hashtags = collect(is_array($answer['hashtags'] ?? null) ? $answer['hashtags'] : [])
            ->map(static fn (mixed $tag): string => '#'.ltrim(preg_replace('/\s+/u', '', (string) $tag) ?? '', '#'))
            ->filter(static fn (string $tag): bool => mb_strlen($tag) > 1)
            ->unique()
            ->take($ad->platform->maxHashtags())
            ->values()
            ->all();

        return [
            'headline' => mb_substr($headline, 0, 255),
            'hook' => mb_substr(trim((string) ($answer['hook'] ?? $scenes[0]['caption'])), 0, 500),
            'scenes' => array_slice($scenes, 0, $count),
            'voiceover' => trim((string) ($answer['voiceover'] ?? '')),
            'caption' => mb_substr(trim((string) ($answer['caption'] ?? $headline)), 0, $ad->platform->maxCaption()),
            'hashtags' => $hashtags,
        ];
    }

    private function scenePrompt(VideoAd $ad, string $product, string $visual, bool $withReference): string
    {
        return 'Advertising video frame ('.$ad->aspect->value.') for "'.$product.'". '.$ad->video_type->direction().' '.$ad->presenter->prompt().' '
            .'Shot: '.$visual.' '
            .($withReference ? 'The product must look exactly like the product in the reference photo (shape, colours, materials). ' : '')
            .'Photorealistic, high-end commercial photography, natural light, sharp focus. No text, letters, captions, logos or watermarks.';
    }

    /**
     * @param  array{images: list<string>, captions: list<string>, audio: string|null}  $assets
     */
    private function assemble(VideoAd $ad, array $assets, Filesystem $disk): void
    {
        $this->advance($ad, VideoAdStatus::Rendering, 85, ['audio_path' => $assets['audio'], 'frames' => $assets['images']]);

        if (! $this->renderer->available()) {
            $this->finish($ad, ['output' => VideoAdOutput::Slideshow, 'poster_path' => $assets['images'][0] ?? null, 'video_path' => null]);

            return;
        }

        $video = $ad->directory().'/ad.mp4';
        $poster = $ad->directory().'/poster.jpg';
        $workDir = $disk->path($ad->directory());

        $this->renderer->render(
            array_map(static fn (string $path): string => $disk->path($path), $assets['images']),
            $assets['captions'],
            $assets['audio'] !== null ? $disk->path($assets['audio']) : null,
            $ad->aspect,
            $ad->duration,
            $workDir,
            $disk->path($video),
            $disk->path($poster),
            in_array(explode('-', $ad->locale)[0], ['ar', 'fa', 'he', 'ur'], true),
        );
        $disk->delete($ad->directory().'/captions.ass');

        $this->finish($ad, ['output' => VideoAdOutput::Video, 'video_path' => $video, 'poster_path' => $poster]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function finish(VideoAd $ad, array $attributes): void
    {
        $this->advance($ad, VideoAdStatus::Ready, 100, $attributes + ['finished_at' => now(), 'error' => null]);
        $this->notify($ad, 'ready');
    }

    private function fail(VideoAd $ad, string $message): void
    {
        $this->advance($ad, VideoAdStatus::Failed, $ad->progress, ['error' => mb_substr($message, 0, 500), 'finished_at' => now()]);
        $this->notify($ad, 'failed');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function advance(VideoAd $ad, VideoAdStatus $status, int $progress, array $attributes = []): void
    {
        $this->ads->update($ad, $attributes + ['status' => $status, 'progress' => max(0, min(100, $progress))]);
        $tenantId = (int) tenant()?->getTenantKey();

        try {
            VideoAdUpdated::dispatch($tenantId, $ad);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function notify(VideoAd $ad, string $event): void
    {
        $recipient = $ad->created_by !== null ? $this->staff->find($ad->created_by) : null;
        $recipients = $recipient instanceof VendorStaff ? collect([$recipient]) : $this->staff->activeOwners();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new VideoAdNotification($event, ['product' => $ad->product_name], vendor_route('marketing.ads.index', ['ad' => $ad->id])));
        }
    }

    /**
     * First gallery image of the product (own: store media; imported: central media, read
     * from the central disk).
     *
     * @return array{binary: string, mime: string, extension: string}|null
     */
    private function productImage(StoreProduct $product): ?array
    {
        $media = $product->isOwn() ? $product->media()->first() : $product->centralProduct?->media()->first();

        if (! $media instanceof CatalogMedia) {
            return null;
        }

        try {
            $binary = $product->isOwn()
                ? Storage::disk($media->disk)->get($media->path)
                : tenancy()->central(static fn (): ?string => Storage::disk($media->disk)->get($media->path));
        } catch (Throwable) {
            return null;
        }

        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

        return isset($extensions[$mime]) ? ['binary' => $binary, 'mime' => $mime, 'extension' => $extensions[$mime]] : null;
    }

    private function languageName(string $locale): string
    {
        return $this->languages->storeLanguages()[$locale] ?? $locale;
    }
}
