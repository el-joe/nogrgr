<?php

use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Models\VendorStaff;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/*
| VEN-034a AI logo generator (OpenAI images, Http::fake): 4 options stored as candidates,
| picking one replaces the store logo, monthly `ai_logos` quota, usage log, permissions.
*/

beforeEach(function (): void {
    Sleep::fake();
    Storage::fake('public');
    Http::preventStrayRequests();
    config(['ai.providers.openai.api_key' => 'sk-test-key', 'ai.providers.openai.base_url' => 'https://api.openai.com/v1', 'ai.providers.openai.image_model' => 'gpt-image-1']);
    $png = base64_encode(UploadedFile::fake()->image('l.png', 64, 64)->getContent());
    Http::fake(['api.openai.com/v1/images/generations' => Http::response([
        'data' => array_fill(0, 4, ['b64_json' => $png]),
        'usage' => ['input_tokens' => 40, 'output_tokens' => 4000],
    ])]);
    $this->tenant = $this->createTenant('ailogo-'.Str::lower(Str::random(6)), ['name' => 'Megz Store']);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/store/appearance'.$path);
    $this->payload = ['store_name' => 'Megz Store', 'tagline' => 'Everyday goods', 'style' => 'modern_minimal', 'color' => 'orange'];
});

it('shows the generator on the logo tab and generates four options', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->get(($this->url)('?tab=logo'))->assertOk()->assertSee('data-ai-logo', false)->assertSee(__('ai::tools.logo.unlimited'));

    $response = $this->postJson(($this->url)('/ai-logo/generate'), $this->payload)->assertOk();

    expect($response->json('data.options'))->toHaveCount(4);
    Http::assertSent(fn (HttpRequest $request): bool => $request['model'] === 'gpt-image-1' && $request['n'] === 4 && str_contains($request['prompt'], 'Megz Store'));
    $this->tenant->run(fn () => expect(Storage::disk('public')->allFiles('ai-logos'))->toHaveCount(4));
    $log = AiUsageLog::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
    expect($log->feature)->toBe(AiFeature::LogoGeneration)->and($log->completion_tokens)->toBe(4000);
});

it('uses a picked option as the store logo and removes the candidates', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $key = $this->postJson(($this->url)('/ai-logo/generate'), $this->payload)->json('data.options.1.key');

    $this->postJson(($this->url)('/ai-logo/apply'), ['option' => $key])
        ->assertOk()
        ->assertJsonPath('data.url', fn (string $url) => str_contains($url, 'branding/logo-'));
    $this->tenant->run(fn () => expect(Storage::disk('public')->allFiles('ai-logos'))->toBe([])->and(Storage::disk('public')->allFiles('branding'))->toHaveCount(1));

    $this->postJson(($this->url)('/ai-logo/apply'), ['option' => $key])->assertUnprocessable();
    $this->postJson(($this->url)('/ai-logo/apply'), ['option' => '../../branding/x'])->assertUnprocessable();
});

it('enforces the monthly AI logo quota', function (): void {
    $plan = Plan::factory()->create(['limits' => ['ai_logos' => 1]]);
    Subscription::factory()->create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id]);
    $this->actingAsVendor($this->owner, $this->tenant);

    $this->postJson(($this->url)('/ai-logo/generate'), $this->payload)->assertOk()->assertJsonPath('data.used_label', __('ai::tools.logo.used', ['used' => 1, 'max' => 1]));
    $this->postJson(($this->url)('/ai-logo/generate'), $this->payload)->assertStatus(429)->assertJsonPath('data.quota.reached', true);
    Http::assertSentCount(1);
});

it('validates the brief and guards the endpoints', function (): void {
    $this->postJson(($this->url)('/ai-logo/generate'), $this->payload)->assertUnauthorized();

    $viewer = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', 'store.appearance.view']);

        return $staff;
    });
    $this->actingAsVendor($viewer, $this->tenant);
    $this->postJson(($this->url)('/ai-logo/generate'), $this->payload)->assertForbidden();
    $this->get(($this->url)('?tab=logo'))->assertOk()->assertDontSee('data-ai-logo', false);

    $this->actingAsVendor($this->owner, $this->tenant);
    $this->postJson(($this->url)('/ai-logo/generate'), ['style' => 'weird'])->assertUnprocessable()->assertJsonValidationErrors(['store_name', 'style', 'color']);
    Http::assertNothingSent();
});
