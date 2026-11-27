<?php

use App\Modules\Identity\Models\Admin;
use App\Modules\Platform\Enums\CacheAction;
use App\Modules\Platform\Enums\CacheRunStatus;
use App\Modules\Platform\Jobs\RunCacheAction;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\CacheClearRun;
use App\Modules\Platform\Services\CacheToolsService;
use App\Modules\Platform\Support\CacheClearer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| Admin › System tools › Cache tools (ADM-114 / 114a / 114b). The framework commands are
| never run for real here (they would touch files shared by parallel workers): the
| CacheClearer is mocked for the flows, store-level clearing is tested on a real store.
*/

function cacheAdmin(): Admin
{
    return vendorsAdmin(['system.cache.view', 'system.cache.manage']);
}

it('protects the cache tools', function (): void {
    $this->get($this->centralUrl('/admin/system/cache'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/system/cache'))->assertForbidden();

    $this->actingAs(vendorsAdmin(['system.cache.view']), 'admin');
    $this->get($this->centralUrl('/admin/system/cache'))->assertOk()->assertDontSee('data-confirm-url', false);
    $this->postJson($this->centralUrl('/admin/system/cache/clear'), ['action' => 'application'])->assertForbidden();
});

it('renders both tabs with the last run of each action', function (): void {
    $admin = cacheAdmin();
    $store = vendorsTenant(['name' => 'Megz Store']);
    CacheClearRun::query()->create(['action' => CacheAction::Views, 'status' => CacheRunStatus::Done, 'admin_id' => $admin->id, 'finished_at' => now()]);
    $this->actingAs($admin, 'admin');

    $this->get($this->centralUrl('/admin/system/cache'))->assertOk()
        ->assertSee(__('platform::cache_tools.actions.application.label'))
        ->assertSee(__('platform::cache_tools.actions.config_routes.button'))
        ->assertSee($admin->name)
        ->assertSee(__('platform::cache_tools.never'));

    $this->get($this->centralUrl('/admin/system/cache?tab=tenants'))->assertOk()
        ->assertSee(__('platform::cache_tools.pick_store_title'))
        ->assertDontSee(__('platform::cache_tools.actions.store_pages.label'));

    $this->get($this->centralUrl("/admin/system/cache?tab=tenants&store={$store->id}"))->assertOk()
        ->assertSee('Megz Store')
        ->assertSee(__('platform::cache_tools.actions.store_pages.label'))
        ->assertSee(__('platform::cache_tools.actions.store_themes.label'));
});

it('clears each main cache, records and audits the run', function (CacheAction $action, string $method): void {
    $this->mock(CacheClearer::class, fn (MockInterface $mock) => $mock->shouldReceive($method)->once());
    $this->actingAs($admin = cacheAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/system/cache/clear'), ['action' => $action->value])
        ->assertOk()
        ->assertJsonPath('data.status', 'done');

    $run = CacheClearRun::query()->sole();
    expect($run->action)->toBe($action)
        ->and($run->status)->toBe(CacheRunStatus::Done)
        ->and($run->admin_id)->toBe($admin->id)
        ->and($run->finished_at)->not->toBeNull();

    $audit = AuditEvent::query()->where('action', CacheToolsService::AUDIT_ACTION)->sole();
    expect($audit->changes['action'])->toBe($action->value);
})->with([
    'application' => [CacheAction::Application, 'application'],
    'config & routes' => [CacheAction::ConfigRoutes, 'configAndRoutes'],
    'views' => [CacheAction::Views, 'views'],
]);

it('records a failed run', function (): void {
    $this->mock(CacheClearer::class, fn (MockInterface $mock) => $mock->shouldReceive('application')->andThrow(new RuntimeException('redis down')));
    $this->actingAs(cacheAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/system/cache/clear'), ['action' => 'application'])->assertStatus(500);

    expect(CacheClearRun::query()->sole())->status->toBe(CacheRunStatus::Failed)->error->toBe('redis down');
});

it('clears one store and requires the store', function (CacheAction $action, string $method): void {
    $store = vendorsTenant();
    $this->mock(CacheClearer::class, fn (MockInterface $mock) => $mock->shouldReceive($method)->once()->withArgs(fn ($tenant) => $tenant->is($store)));
    $this->actingAs(cacheAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/system/cache/clear'), ['action' => $action->value])->assertJsonValidationErrors('tenant_id');
    $this->postJson($this->centralUrl('/admin/system/cache/clear?action='.$action->value.'&tenant_id='.$store->id))->assertOk();

    expect(CacheClearRun::query()->sole())->tenant_id->toBe($store->id)->status->toBe(CacheRunStatus::Done);
})->with([
    'pages' => [CacheAction::StorePages, 'storePages'],
    'translations' => [CacheAction::StoreTranslations, 'storeTranslations'],
    'themes' => [CacheAction::StoreThemes, 'storeThemes'],
]);

it('queues the all-stores clear and finishes the run in the job', function (): void {
    Queue::fake();
    $this->actingAs(cacheAdmin(), 'admin');
    $first = vendorsTenant();
    $second = vendorsTenant();

    $this->postJson($this->centralUrl('/admin/system/cache/clear'), ['action' => 'all_stores'])->assertOk()->assertJsonPath('data.status', 'queued');

    $run = CacheClearRun::query()->sole();
    expect($run->status)->toBe(CacheRunStatus::Queued);
    Queue::assertPushed(RunCacheAction::class, fn (RunCacheAction $job) => $job->runId === $run->id);

    $this->mock(CacheClearer::class, function (MockInterface $mock): void {
        $mock->shouldReceive('storePages')->twice();
        $mock->shouldReceive('storeThemes')->twice();
        $mock->shouldReceive('storeTranslations')->twice();
    });
    app()->call([new RunCacheAction($run->id), 'handle']);

    expect($run->refresh())->status->toBe(CacheRunStatus::Done)->stores->toBe(2);
});

it('flushes the store tag, the store texts and the compiled theme of one real store', function (): void {
    Storage::fake('custom-themes');
    $tenant = $this->createTenant('cch-'.Str::lower(Str::random(6)));
    $other = $this->createTenant('cch-'.Str::lower(Str::random(6)));
    Storage::disk('custom-themes')->put("compiled/{$tenant->id}/1/a.php", 'x');
    Storage::disk('custom-themes')->put("compiled/{$other->id}/1/a.php", 'x');

    $clearer = app(CacheClearer::class);
    // runs inside the store (static texts); the array cache is rebuilt when tenancy ends
    $clearer->storeTranslations($tenant);

    $tag = fn ($store) => Cache::store()->tags([config('tenancy.cache.tag_base').$store->id]);
    $tag($tenant)->put('home-blocks', 'cached');
    $tag($other)->put('home-blocks', 'cached');

    $clearer->storePages($tenant);
    $clearer->storeThemes($tenant);

    expect($tag($tenant)->get('home-blocks'))->toBeNull()
        ->and($tag($other)->get('home-blocks'))->toBe('cached');
    Storage::disk('custom-themes')->assertMissing("compiled/{$tenant->id}/1/a.php");
    Storage::disk('custom-themes')->assertExists("compiled/{$other->id}/1/a.php");
});
