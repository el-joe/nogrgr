<?php

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Enums\VideoAdOutput;
use App\Modules\Ai\Enums\VideoAdStatus;
use App\Modules\Ai\Jobs\GenerateVideoAd;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Models\VideoAd;
use App\Modules\Ai\Notifications\VideoAdNotification;
use App\Modules\Ai\Services\VideoAds\VideoAdGenerator;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Catalog\Models\Product;
use App\Modules\Localization\Services\TenantLanguageService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

require_once __DIR__.'/../Catalog/Vendor/VendorCatalogHelpers.php';

/*
| VEN-010c AI Advertising Studio + VEN-009e dashboard widget (OpenAI only, Http::fake,
| ffmpeg through Process::fake): queued generation, live status, monthly `ai_ads` quota
| (incl. ads still generating), one usage row per ad, MP4 / slideshow output, download,
| retry, delete, permissions and tenant isolation.
*/

beforeEach(function (): void {
    Sleep::fake();
    Storage::fake('public');
    Http::preventStrayRequests();
    config([
        'ai.providers.openai.api_key' => 'sk-test-key',
        'ai.providers.openai.base_url' => 'https://api.openai.com/v1',
        'ai.providers.openai.model' => 'gpt-test',
        'ai.video_ads.ffmpeg' => PHP_BINARY,
    ]);
    $this->down = false;
    $png = base64_encode(UploadedFile::fake()->image('s.png', 32, 48)->getContent());
    Http::fake([
        'api.openai.com/v1/chat/completions' => fn () => $this->down
            ? Http::response(['error' => ['message' => 'bad key', 'code' => 'invalid_api_key']], 401)
            : Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'headline' => 'Train harder, track smarter',
                    'hook' => 'Your workout deserves a coach on your wrist.',
                    'scenes' => [
                        ['caption' => 'Stop guessing', 'visual' => 'Runner checks a watch at dawn'],
                        ['caption' => 'Heart rate live', 'visual' => 'Close-up of the watch face'],
                        ['caption' => 'Waterproof', 'visual' => 'Watch splashed with water'],
                        ['caption' => 'Shop now', 'visual' => 'Watch on a podium'],
                    ],
                    'voiceover' => 'Meet the smartwatch that trains with you.',
                    'caption' => 'Level up every workout.',
                    'hashtags' => ['fitness', '#smartwatch', 'fitness'],
                ])]]],
                'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 90],
            ]),
        'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => $png]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 100]]),
        'api.openai.com/v1/audio/speech' => Http::response('ID3-fake-mp3', 200, ['Content-Type' => 'audio/mpeg']),
    ]);
    Process::fake(function (PendingProcess $process) {
        $command = (array) $process->command;
        file_put_contents((string) end($command), 'rendered');

        return Process::result();
    });

    [$this->tenant, $this->owner, $this->sa] = catalogStore('adstudio-'.Str::lower(Str::random(6)));
    $this->tenant->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());
    $this->product = storeProduct($this->tenant, Product::factory()->create(['name' => ['en' => 'Sports Smartwatch']])->id);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/marketing/ads'.$path);
    $this->payload = fn (array $overrides = []) => array_merge([
        'product_id' => $this->product->id,
        'type' => 'product_only',
        'presenter' => 'none',
        'voice' => 'female',
        'tone' => 'energetic',
        'locale' => 'en',
        'platform' => 'tiktok',
        'aspect' => '9:16',
        'duration' => 15,
        'country_id' => $this->sa->id,
        'notes' => 'Mention the waterproof design',
    ], $overrides);
});

function adStudioAd(object $test, array $attributes = []): VideoAd
{
    return $test->tenant->run(fn (): VideoAd => VideoAd::query()->create(array_merge([
        'store_product_id' => $test->product->id,
        'product_name' => 'Sports Smartwatch',
        'video_type' => 'product_only',
        'presenter' => 'none',
        'voice' => 'female',
        'tone' => 'energetic',
        'locale' => 'en',
        'platform' => 'tiktok',
        'aspect' => '9:16',
        'duration' => 15,
        'status' => VideoAdStatus::Queued,
    ], $attributes)));
}

it('queues an ad from the four wizard steps', function (): void {
    Queue::fake();

    $response = $this->actingAsVendor($this->owner, $this->tenant)
        ->postJson(($this->url)(), ($this->payload)())
        ->assertOk()
        ->assertJsonPath('data.ad.status', 'queued')
        ->assertJsonPath('data.ad.active', true)
        ->assertJsonPath('data.ad.product', 'Sports Smartwatch');

    Queue::assertPushed(GenerateVideoAd::class, fn (GenerateVideoAd $job): bool => $job->adId === $response->json('data.ad.id'));
    $this->tenant->run(function (): void {
        $ad = VideoAd::query()->sole();
        expect($ad->country_id)->toBe($this->sa->id)->and($ad->notes)->toBe('Mention the waterproof design');
    });
    Http::assertNothingSent();
});

it('validates the steps and the size of the platform', function (): void {
    Queue::fake();
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)(), ($this->payload)(['aspect' => '16:9']))->assertUnprocessable()->assertJsonValidationErrors(['aspect']);
    $this->postJson(($this->url)(), ($this->payload)(['type' => 'weird', 'locale' => 'zz', 'duration' => 20]))->assertUnprocessable()->assertJsonValidationErrors(['type', 'locale', 'duration']);
    $this->postJson(($this->url)(), ($this->payload)(['product_id' => $this->product->id + 999]))->assertUnprocessable();
    Queue::assertNothingPushed();
});

it('generates the script, scenes, voice-over and MP4 in one metered OpenAI run', function (): void {
    Notification::fake();
    $ad = adStudioAd($this, ['created_by' => $this->owner->id, 'country_id' => $this->sa->id]);

    $this->tenant->run(fn () => (new GenerateVideoAd($ad->id))->handle(app(VideoAdGenerator::class)));

    $this->tenant->run(function () use ($ad): void {
        $fresh = $ad->fresh();
        expect($fresh->status)->toBe(VideoAdStatus::Ready)
            ->and($fresh->progress)->toBe(100)
            ->and($fresh->output)->toBe(VideoAdOutput::Video)
            ->and($fresh->headline)->toBe('Train harder, track smarter')
            ->and($fresh->hashtags)->toBe(['#fitness', '#smartwatch'])
            ->and($fresh->scenes)->toHaveCount(4)
            ->and(Storage::disk('public')->exists($fresh->video_path))->toBeTrue()
            ->and(Storage::disk('public')->exists($fresh->poster_path))->toBeTrue()
            ->and(Storage::disk('public')->exists($fresh->audio_path))->toBeTrue();
    });

    Http::assertSentCount(6);
    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/chat/completions') && str_contains(json_encode($request->data()), 'Sports Smartwatch') && str_contains(json_encode($request->data()), 'Exactly 4 scenes'));
    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/images/generations') && $request['size'] === '1024x1536');
    Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/audio/speech') && $request['voice'] === 'nova');
    Process::assertRan(fn (PendingProcess $process): bool => in_array('libx264', (array) $process->command, true));

    $log = AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
    expect($log->feature)->toBe(AiFeature::VideoAd)->and($log->subject)->toBe('video_ad:'.$ad->id)->and($log->prompt_tokens)->toBe(160);
    Notification::assertSentTo($this->owner, VideoAdNotification::class, fn (VideoAdNotification $notification): bool => $notification->eventName === 'ready');

    $this->actingAsVendor($this->owner, $this->tenant)
        ->getJson(($this->url)('/'.$ad->id))
        ->assertOk()
        ->assertJsonPath('data.ad.status', 'ready')
        ->assertJsonPath('data.ad.output', 'video')
        ->assertJsonPath('data.ad.urls.download', fn (string $url): bool => str_ends_with($url, '/ads/'.$ad->id.'/download'));
    $this->get(($this->url)('/'.$ad->id.'/download'))->assertOk()->assertDownload('ad-'.$ad->id.'.mp4');
});

it('falls back to an animated slideshow without ffmpeg and downloads it as a ZIP', function (): void {
    config(['ai.video_ads.ffmpeg' => '/nonexistent/ffmpeg']);
    $ad = adStudioAd($this, ['voice' => 'none']);

    $this->tenant->run(fn () => app(VideoAdGenerator::class)->generate($ad->id));

    $this->tenant->run(function () use ($ad): void {
        $fresh = $ad->fresh();
        expect($fresh->status)->toBe(VideoAdStatus::Ready)
            ->and($fresh->output)->toBe(VideoAdOutput::Slideshow)
            ->and($fresh->frames)->toHaveCount(4)
            ->and($fresh->audio_path)->toBeNull();
    });
    Http::assertNotSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/audio/speech'));
    Process::assertNothingRan();

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)('/'.$ad->id.'/download'))->assertOk()->assertDownload('ad-'.$ad->id.'.zip');
});

it('marks the ad failed on a provider error without using the quota, and retries it', function (): void {
    Notification::fake();
    $this->down = true;
    $ad = adStudioAd($this, ['created_by' => $this->owner->id]);

    $this->tenant->run(fn () => app(VideoAdGenerator::class)->generate($ad->id));

    $this->tenant->run(fn () => expect($ad->fresh()->status)->toBe(VideoAdStatus::Failed)->and($ad->fresh()->error)->toBe(__('ai::ad_studio.errors.provider')));
    expect(AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(0);
    Notification::assertSentTo($this->owner, VideoAdNotification::class, fn (VideoAdNotification $notification): bool => $notification->eventName === 'failed');

    Queue::fake();
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->url)('/'.$ad->id.'/retry'))->assertOk()->assertJsonPath('data.ad.status', 'queued');
    Queue::assertPushed(GenerateVideoAd::class);
    $this->postJson(($this->url)('/'.$ad->id.'/retry'))->assertUnprocessable();
});

it('enforces the monthly AI ads quota, counting ads still being generated', function (): void {
    Queue::fake();
    $plan = Plan::factory()->create(['limits' => ['ai_ads' => 1]]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]);
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)(), ($this->payload)())->assertOk()->assertJsonPath('data.quota.max', 1);
    $this->postJson(($this->url)(), ($this->payload)())->assertStatus(429)->assertJsonPath('data.quota.reached', false)->assertJsonPath('message', fn (string $message): bool => $message !== '');
    Queue::assertPushed(GenerateVideoAd::class, 1);

    // A finished (logged) ad uses the month's quota too.
    $this->tenant->run(fn () => VideoAd::query()->update(['status' => VideoAdStatus::Ready->value]));
    AiUsageLog::query()->create(['tenant_id' => $this->tenant->id, 'feature' => AiFeature::VideoAd, 'provider' => 'openai', 'model' => 'gpt-test', 'prompt_tokens' => 1, 'completion_tokens' => 1]);
    $this->postJson(($this->url)(), ($this->payload)())->assertStatus(429)->assertJsonPath('data.quota.reached', true);
});

it('renders the studio page, library data and the dashboard widget', function (): void {
    $ready = adStudioAd($this, ['status' => VideoAdStatus::Ready, 'progress' => 100, 'headline' => 'Train harder', 'hook' => 'Your wrist coach', 'output' => VideoAdOutput::Video, 'poster_path' => 'ai-ads/1/poster.jpg', 'video_path' => 'ai-ads/1/ad.mp4']);
    adStudioAd($this, ['status' => VideoAdStatus::Visuals, 'progress' => 40]);
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->get(($this->url)())->assertOk()
        ->assertSee(__('ai::ad_studio.library.title'))
        ->assertSee('data-ad-studio-config', false)
        ->assertSee('id="ad-studio-modal"', false)
        ->assertSee(__('ai::ad_studio.steps.people'));

    $this->getJson(($this->url)('/data?draw=1&start=0&length=10'))->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->getJson(($this->url)('/data?draw=1&start=0&length=10&status=queued'))->assertOk()->assertJsonPath('recordsFiltered', 1);

    $this->get($this->tenantUrl($this->tenant, 'admin/dashboard'))->assertOk()
        ->assertSee(__('ai::ad_studio.widget.title'))
        ->assertSee('Train harder')
        ->assertSee('data-ad-preview="'.$ready->id.'"', false);
});

it('shows the empty widget and deletes a finished ad with its files', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->get($this->tenantUrl($this->tenant, 'admin/dashboard'))->assertOk()->assertSee(__('ai::ad_studio.widget.empty_title'));

    $ad = adStudioAd($this, ['status' => VideoAdStatus::Failed]);
    $this->tenant->run(fn () => Storage::disk('public')->put($ad->directory().'/scene-1.png', 'x'));
    $active = adStudioAd($this, ['status' => VideoAdStatus::Rendering]);

    $this->deleteJson(($this->url)('/'.$active->id))->assertUnprocessable();
    $this->deleteJson(($this->url)('/'.$ad->id))->assertOk();
    $this->tenant->run(fn () => expect(VideoAd::query()->find($ad->id))->toBeNull()->and(Storage::disk('public')->allFiles($ad->directory()))->toBe([]));
});

it('refuses guests and staff without the studio permissions', function (): void {
    Queue::fake();
    $this->get(($this->url)())->assertRedirect();
    $this->postJson(($this->url)(), ($this->payload)())->assertUnauthorized();

    $outsider = catalogStaff($this->tenant, ['catalog.products.view']);
    $this->actingAsVendor($outsider, $this->tenant)->get(($this->url)())->assertForbidden();

    $viewer = catalogStaff($this->tenant, ['marketing.ads.view']);
    $this->actingAsVendor($viewer, $this->tenant)->get(($this->url)())->assertOk()->assertDontSee('id="ad-studio-modal"', false);
    $this->postJson(($this->url)(), ($this->payload)())->assertForbidden();
    $this->getJson(($this->url)('/products'))->assertForbidden();
    Queue::assertNothingPushed();
});

it('keeps another store\'s ads out of reach', function (): void {
    [$other, $otherOwner] = catalogStore('adstudio-o-'.Str::lower(Str::random(6)));
    $foreign = $other->run(fn (): VideoAd => VideoAd::query()->create([
        'product_name' => 'Foreign', 'video_type' => 'ugc', 'presenter' => 'none', 'voice' => 'none', 'tone' => 'calm',
        'locale' => 'en', 'platform' => 'x', 'aspect' => '16:9', 'duration' => 15, 'status' => VideoAdStatus::Ready,
    ]));
    $mine = adStudioAd($this);

    $this->actingAsVendor($this->owner, $this->tenant);
    $id = $foreign->id === $mine->id ? $foreign->id + 100 : $foreign->id;
    $this->getJson(($this->url)('/'.$id))->assertNotFound();
    $this->get(($this->url)('/'.$id.'/download'))->assertNotFound();
    $this->deleteJson(($this->url)('/'.$id))->assertNotFound();
    $other->run(fn () => expect(VideoAd::query()->count())->toBe(1));
});

it('lists the store products with their image for the picker', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)
        ->getJson(($this->url)('/products?q=Smart'))
        ->assertOk()
        ->assertJsonPath('results.0.id', $this->product->id)
        ->assertJsonPath('results.0.text', 'Sports Smartwatch');
});
