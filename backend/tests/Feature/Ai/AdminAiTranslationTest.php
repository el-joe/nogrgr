<?php

use App\Modules\Ai\Enums\AdminTranslationKind;
use App\Modules\Ai\Enums\AdminTranslationRunStatus;
use App\Modules\Ai\Enums\AiFeature;
use App\Modules\Ai\Models\AdminTranslationRun;
use App\Modules\Ai\Models\AiUsageLog;
use App\Modules\Ai\Notifications\AdminTranslationRunNotification;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Integrations\Enums\IntegrationSection;
use App\Modules\Localization\Jobs\TranslateMissingStaticTexts;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Models\TranslationKeyLock;
use App\Modules\Localization\Models\TranslationOverride;
use App\Modules\Localization\Services\TenantLanguageService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/*
| P10 admin AI translation (ADM-091b catalogue, ADM-092a static keys): custom provider
| from DB settings (FreeLLMAPI, decision #44 a), only missing texts filled, locked keys
| skipped, placeholders preserved, resume after a failure, permissions, usage log.
*/

const ADMIN_AI_FILE = 'ai::admin_translation';

beforeEach(function (): void {
    Sleep::fake();
    Notification::fake();
    Http::preventStrayRequests();
    $this->useSettings(IntegrationSection::Ai, [
        'custom_provider' => 'freellmapi',
        'paid_provider' => 'openai',
        'freellmapi_base_url' => 'http://free-llm.test/v1',
        'freellmapi_model' => 'auto',
    ]);

    $this->sent = [];
    $this->failWhen = null;
    $this->dropPlaceholders = false;
    Http::fake(['http://free-llm.test/v1/*' => function (HttpRequest $request) {
        $texts = json_decode($request['messages'][1]['content'], true)['texts'];

        if ($this->failWhen !== null && collect($texts)->contains(fn (string $text): bool => str_contains($text, $this->failWhen))) {
            return Http::response(['error' => ['message' => 'down']], 503);
        }

        array_push($this->sent, ...array_values($texts));
        $translate = fn (string $text): string => '[fr] '.($this->dropPlaceholders ? str_replace('{{ var }}', '', $text) : $text);

        return Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['translations' => array_map($translate, $texts)], JSON_UNESCAPED_UNICODE)]]],
            'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 5],
        ]);
    }]);

    $this->french = Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français']);
    $this->french->update(['is_active' => true]);
});

function aiTranslationAdmin(array $permissions = ['localization.languages.manage', 'localization.translations.manage']): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(array_values(array_merge(['dashboard.view'], $permissions)));

    return $admin;
}

function aiTranslationUrl(string $path, array $query = []): string
{
    return test()->centralUrl('/admin/localization/'.ltrim($path, '/')).($query === [] ? '' : '?'.http_build_query($query));
}

function frenchOverride(string $key): ?string
{
    return TranslationOverride::query()->where('file', ADMIN_AI_FILE)->where('key', $key)->where('locale', 'fr')->value('value');
}

it('fills only the missing catalogue texts of the language and never overwrites existing ones', function (): void {
    $admin = aiTranslationAdmin();
    $product = Product::factory()->create(['name' => ['en' => 'Desk lamp', 'fr' => 'Lampe de bureau'], 'summary' => ['en' => 'Only {{ count }} left at :price'], 'description' => null, 'meta_title' => null, 'meta_description' => null]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'title' => ['en' => 'Large']]);
    $category = Category::factory()->create(['name' => ['ar' => 'أحذية'], 'description' => null, 'meta_title' => null, 'meta_description' => null]);

    $response = $this->actingAs($admin, 'admin')
        ->postJson(aiTranslationUrl('ai-translation/catalog'), ['locale' => 'fr'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $run = AdminTranslationRun::query()->findOrFail($response->json('data.run'));
    expect($run->kind)->toBe(AdminTranslationKind::Catalog)
        ->and($run->failed_items)->toBe(0)
        ->and($run->done_items)->toBe($run->total_items)
        ->and($run->provider)->toBe('freellmapi')
        ->and($run->prompt_tokens)->toBeGreaterThan(0)
        ->and($product->fresh()->getTranslation('name', 'fr', false))->toBe('Lampe de bureau')
        ->and($product->fresh()->getTranslation('summary', 'fr', false))->toBe('[fr] Only {{ count }} left at :price')
        ->and($product->fresh()->getTranslation('name', 'en', false))->toBe('Desk lamp')
        ->and($variant->fresh()->getTranslation('title', 'fr', false))->toBe('[fr] Large')
        ->and($category->fresh()->getTranslation('name', 'fr', false))->toBe('[fr] أحذية')
        ->and($this->sent)->not->toContain('Desk lamp');

    Http::assertSent(fn (HttpRequest $request): bool => str_starts_with($request->url(), 'http://free-llm.test/v1/chat/completions') && $request['model'] === 'auto');
    expect(AiUsageLog::query()->where('feature', AiFeature::CatalogTranslation->value)->whereNull('tenant_id')->where('subject', 'admin-run:'.$run->id)->exists())->toBeTrue();
    Notification::assertSentTo($admin, AdminTranslationRunNotification::class, fn (AdminTranslationRunNotification $notification): bool => $notification->eventName === 'translation_completed');
});

it('resumes a failed catalogue run with only the texts still missing', function (): void {
    $admin = aiTranslationAdmin();
    $product = Product::factory()->create(['name' => ['en' => 'Garden chair'], 'summary' => null, 'description' => null, 'meta_title' => null, 'meta_description' => null]);
    $category = Category::factory()->create(['name' => ['en' => 'Broken outdoor'], 'description' => null, 'meta_title' => null, 'meta_description' => null]);
    $this->failWhen = 'Broken outdoor';

    $runId = $this->actingAs($admin, 'admin')->postJson(aiTranslationUrl('ai-translation/catalog'), ['locale' => 'fr'])->assertOk()->json('data.run');
    $run = AdminTranslationRun::query()->findOrFail($runId);

    expect($run->status)->toBe(AdminTranslationRunStatus::Failed)
        ->and($run->failed_items)->toBeGreaterThan(0)
        ->and($run->sections['categories']['failed'])->toBeGreaterThan(0)
        ->and($product->fresh()->getTranslation('name', 'fr', false))->toBe('[fr] Garden chair')
        ->and($category->fresh()->getTranslation('name', 'fr', false))->toBe('');
    Notification::assertSentTo($admin, AdminTranslationRunNotification::class, fn (AdminTranslationRunNotification $notification): bool => $notification->eventName === 'translation_failed');

    $this->failWhen = null;
    $this->sent = [];
    $this->actingAs($admin, 'admin')->postJson(aiTranslationUrl('ai-translation/runs/'.$run->id.'/retry'))
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($run->fresh()->attempts)->toBe(1)
        ->and($run->fresh()->failed_items)->toBe(0)
        ->and($category->fresh()->getTranslation('name', 'fr', false))->toBe('[fr] Broken outdoor')
        ->and($this->sent)->toContain('Broken outdoor')
        ->and($this->sent)->not->toContain('Garden chair');
});

it('translates missing unlocked static keys of a file, keeps placeholders and skips locked or filled keys', function (): void {
    $admin = aiTranslationAdmin();
    TranslationKeyLock::query()->create(['file' => ADMIN_AI_FILE, 'key' => 'started']);
    TranslationOverride::query()->create(['file' => ADMIN_AI_FILE, 'key' => 'stopped', 'locale' => 'fr', 'value' => 'Arrêtée']);

    $this->actingAs($admin, 'admin')
        ->get(aiTranslationUrl('ai-translation/static-keys/create', ['file' => ADMIN_AI_FILE, 'locale' => 'fr']))
        ->assertOk()
        ->assertSee('data-ai-keys-form', false)
        ->assertSee(__('ai::admin_translation.keys.skipped', ['count' => 1]));

    $runId = $this->actingAs($admin, 'admin')
        ->postJson(aiTranslationUrl('ai-translation/static-keys'), ['locale' => 'fr', 'file' => ADMIN_AI_FILE, 'scope' => 'file'])
        ->assertOk()
        ->json('data.run');

    $run = AdminTranslationRun::query()->findOrFail($runId);
    expect($run->status)->toBe(AdminTranslationRunStatus::Completed)
        ->and($run->scopeFile())->toBe(ADMIN_AI_FILE)
        ->and(frenchOverride('progress.title_catalog'))->toBe('[fr] '.trans('ai::admin_translation.progress.title_catalog', [], 'en'))
        ->and(frenchOverride('progress.title_catalog'))->toContain(':language')
        ->and(frenchOverride('started'))->toBeNull()
        ->and(frenchOverride('stopped'))->toBe('Arrêtée')
        ->and($this->sent)->not->toContain(trans('ai::admin_translation.started', [], 'en'))
        ->and($this->sent)->not->toContain(trans('ai::admin_translation.stopped', [], 'en'));
    expect(AiUsageLog::query()->where('feature', AiFeature::StaticKeyTranslation->value)->whereNull('tenant_id')->exists())->toBeTrue();
});

it('rejects translations that lose placeholders and fails the run for those keys only', function (): void {
    $admin = aiTranslationAdmin();
    $this->dropPlaceholders = true;

    $runId = $this->actingAs($admin, 'admin')
        ->postJson(aiTranslationUrl('ai-translation/static-keys'), ['locale' => 'fr', 'file' => ADMIN_AI_FILE, 'scope' => 'file'])
        ->assertOk()
        ->json('data.run');

    $run = AdminTranslationRun::query()->findOrFail($runId);
    expect($run->status)->toBe(AdminTranslationRunStatus::Failed)
        ->and($run->failed_items)->toBe(1)
        ->and(frenchOverride('keys.hint'))->toBeNull()
        ->and(frenchOverride('keys.title'))->toBe('[fr] Translate with AI');
});

it('suggests one key without saving it and refuses locked keys', function (): void {
    $admin = aiTranslationAdmin();
    TranslationKeyLock::query()->create(['file' => ADMIN_AI_FILE, 'key' => 'banner.failed']);

    $this->actingAs($admin, 'admin')
        ->postJson(aiTranslationUrl('ai-translation/static-keys/suggest'), ['file' => ADMIN_AI_FILE, 'key' => 'banner.view', 'locale' => 'fr'])
        ->assertOk()
        ->assertJsonPath('data.text', '[fr] View progress');
    expect(frenchOverride('banner.view'))->toBeNull()
        ->and(AiUsageLog::query()->where('feature', AiFeature::StaticKeyTranslation->value)->where('subject', 'key:'.ADMIN_AI_FILE.'|banner.view')->exists())->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->postJson(aiTranslationUrl('ai-translation/static-keys/suggest'), ['file' => ADMIN_AI_FILE, 'key' => 'banner.failed', 'locale' => 'fr'])
        ->assertStatus(422);
});

it('shows the run progress, stops an active run and renders the page entry points', function (): void {
    $admin = aiTranslationAdmin(['localization.languages.manage', 'localization.translations.manage']);
    $run = AdminTranslationRun::query()->create(['kind' => AdminTranslationKind::Catalog, 'locale' => 'fr', 'status' => AdminTranslationRunStatus::Translating, 'total_items' => 10, 'done_items' => 4, 'progress' => 40]);

    $this->actingAs($admin, 'admin')->getJson(aiTranslationUrl('ai-translation/runs/'.$run->id))
        ->assertOk()
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.status_url', route('admin.localization.ai-translation.runs.show', $run));

    $this->actingAs($admin, 'admin')->get(aiTranslationUrl('languages'))
        ->assertOk()
        ->assertSee('data-page="admin-languages"', false)
        ->assertSee('data-ai-run-open', false)
        ->assertSee('id="ai-run-modal"', false);

    $this->actingAs($admin, 'admin')->get(aiTranslationUrl('translations', ['file' => ADMIN_AI_FILE, 'locale' => 'fr']))
        ->assertOk()
        ->assertSee(route('admin.localization.ai-translation.static-keys.create', ['file' => ADMIN_AI_FILE, 'locale' => 'fr']));

    $this->actingAs($admin, 'admin')->postJson(aiTranslationUrl('ai-translation/runs/'.$run->id.'/stop'))
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
    expect($run->fresh()->status)->toBe(AdminTranslationRunStatus::Cancelled);
});

dataset('admin ai translation endpoints', [
    'catalog' => ['post', 'ai-translation/catalog', [], 'localization.languages.view'],
    'keys modal' => ['get', 'ai-translation/static-keys/create', ['file' => ADMIN_AI_FILE, 'locale' => 'fr'], 'localization.translations.view'],
    'keys run' => ['post', 'ai-translation/static-keys', [], 'localization.translations.view'],
    'suggest' => ['post', 'ai-translation/static-keys/suggest', [], 'localization.translations.view'],
]);

it('redirects guests to the admin login', function (string $method, string $path, array $query): void {
    $this->{$method}(aiTranslationUrl($path, $query))->assertRedirect($this->centralUrl('/admin/login'));
})->with('admin ai translation endpoints');

it('forbids admins without the manage permission', function (string $method, string $path, array $query, string $viewOnly): void {
    $this->actingAs(aiTranslationAdmin([$viewOnly]), 'admin')->{$method.'Json'}(aiTranslationUrl($path, $query))->assertForbidden();
})->with('admin ai translation endpoints');

it('limits a run to admins with the permission of its kind', function (): void {
    $run = AdminTranslationRun::query()->create(['kind' => AdminTranslationKind::StaticKeys, 'locale' => 'fr', 'status' => AdminTranslationRunStatus::Failed]);

    $this->getJson(aiTranslationUrl('ai-translation/runs/'.$run->id))->assertUnauthorized();
    $this->actingAs(aiTranslationAdmin(['localization.languages.manage']), 'admin')->getJson(aiTranslationUrl('ai-translation/runs/'.$run->id))->assertForbidden();
    $this->actingAs(aiTranslationAdmin(['localization.translations.view']), 'admin')->getJson(aiTranslationUrl('ai-translation/runs/'.$run->id))->assertOk();
    $this->actingAs(aiTranslationAdmin(['localization.translations.view']), 'admin')->postJson(aiTranslationUrl('ai-translation/runs/'.$run->id.'/retry'))->assertForbidden();
});

it('logs the tokens of the vendor "translate missing static texts" job for the store', function (): void {
    $tenant = $this->createTenant('aist-'.Str::lower(Str::random(6)));
    $tenant->run(fn () => app(TenantLanguageService::class)->enableCoreLanguages());

    TranslateMissingStaticTexts::dispatchSync((int) $tenant->id, 'fr');

    expect(AiUsageLog::query()->withoutGlobalScopes()->where('feature', AiFeature::StaticKeyTranslation->value)->where('tenant_id', $tenant->id)->where('subject', 'store-static:fr')->exists())->toBeTrue();
});
