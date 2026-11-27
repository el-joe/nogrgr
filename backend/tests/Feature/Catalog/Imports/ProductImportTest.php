<?php

use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Catalog\Jobs\ConvertCatalogMedia;
use App\Modules\Catalog\Jobs\Imports\ImportCatalogPage;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\CatalogImportCategoryMap;
use App\Modules\Catalog\Models\CatalogImportLog;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Models\CatalogMedia;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Notifications\CatalogImportFinishedNotification;
use App\Modules\Catalog\Services\Imports\CatalogImportService;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Models\BranchStock;
use Illuminate\Bus\PendingBatch;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../CatalogHelpers.php';

/*
| Product import (ADM-013): permissions + validation of every endpoint, the Neozena
| driver over a faked API, idempotent re-runs, per-item error logging, unmapped
| categories, pause / resume / cancel, category mapping and the Integrations card.
*/

const NEOZENA_BASE = 'https://neozena.test/api/catalog';

function configureNeozena(string $baseUrl = NEOZENA_BASE): void
{
    app(SettingsRepositoryContract::class)->put('neozena', ['base_url' => $baseUrl, 'api_token' => 'test-token'], ['api_token']);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function neozenaProduct(int $id, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'sku' => 'NZ-'.$id,
        'status' => true,
        'current_price' => '24.00',
        'weight' => 350,
        'stock' => null,
        'stock_unlimited' => false,
        'taxable' => true,
        'shippable' => true,
        'translations' => [
            ['locale' => 'en', 'title' => 'Linen Shirt '.$id, 'summary' => 'Light shirt', 'description' => 'Breathable linen', 'meta_keywords' => '[{"value":"linen"},{"value":"shirt"}]'],
            ['locale' => 'ar', 'title' => 'قميص كتان '.$id],
        ],
        'categories' => [['id' => 501, 'parent_id' => null, 'translations' => [['locale' => 'en', 'name' => 'Shirts'], ['locale' => 'ar', 'name' => 'قمصان']]]],
        'variants' => [[
            'id' => 70,
            'translations' => [['locale' => 'en', 'name' => 'Size'], ['locale' => 'ar', 'name' => 'المقاس']],
            'options' => [
                ['id' => 701, 'value' => 'M', 'translations' => [['locale' => 'en', 'name' => 'M']]],
                ['id' => 702, 'value' => 'L', 'translations' => [['locale' => 'en', 'name' => 'L']]],
            ],
        ]],
        'variant_combinations' => [
            ['id' => $id * 10 + 1, 'sku' => 'NZ-'.$id.'-M', 'price' => '24.00', 'stock' => 4, 'weight' => 350, 'is_active' => true, 'option_ids' => [701]],
            ['id' => $id * 10 + 2, 'sku' => 'NZ-'.$id.'-L', 'price' => '26.50', 'stock' => 6, 'weight' => 360, 'is_active' => true, 'option_ids' => [702]],
        ],
        'images' => [
            ['id' => $id * 100 + 1, 'image_path' => 'https://cdn.neozena.test/'.$id.'-1.png', 'type' => 'image', 'is_primary' => true],
            ['id' => $id * 100 + 2, 'image_path' => 'https://cdn.neozena.test/'.$id.'-2.png', 'type' => 'image', 'is_primary' => false],
            ['id' => $id * 100 + 3, 'image_path' => 'https://cdn.neozena.test/'.$id.'.mp4', 'type' => 'video', 'is_primary' => false],
        ],
    ], $overrides);
}

/**
 * Fakes the catalog API with the given pages (list of product rows per page) and the CDN;
 * a later call swaps the pages served by the same fake.
 *
 * @param  list<list<array<string, mixed>>>  $pages
 */
function fakeNeozena(array $pages): void
{
    $fresh = ! isset(test()->neozenaPages);
    test()->neozenaPages = $pages;

    if (! $fresh) {
        return;
    }

    $png = UploadedFile::fake()->image('p.png', 8, 8)->getContent();

    Http::fake(function (Request $request) use ($png) {
        if (str_starts_with($request->url(), 'https://cdn.neozena.test/')) {
            return Http::response($png, 200, ['Content-Type' => 'image/png']);
        }

        if (str_starts_with($request->url(), NEOZENA_BASE.'/products')) {
            $pages = test()->neozenaPages;
            $page = (int) ($request->data()['page'] ?? 1);

            return Http::response(['data' => $pages[$page - 1] ?? [], 'meta' => ['current_page' => $page, 'last_page' => count($pages), 'total' => array_sum(array_map('count', $pages))]]);
        }

        return Http::response('', 404);
    });
}

function mapNeozenaCategory(int $externalId = 501): Category
{
    $category = Category::factory()->create();
    CatalogImportCategoryMap::query()->create(['driver' => 'neozena', 'external_id' => $externalId, 'name' => ['en' => 'Shirts'], 'category_id' => $category->id]);

    return $category;
}

function runImport(array $payload = []): CatalogImportRun
{
    test()->postJson(test()->centralUrl('/admin/catalog/imports'), ['driver' => 'neozena', ...$payload])->assertCreated();

    return CatalogImportRun::query()->latest('id')->firstOrFail();
}

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake([ConvertCatalogMedia::class]);
    Notification::fake();
});

dataset('import endpoints', [
    'index' => ['get', '/admin/catalog/imports', 'view'],
    'runs' => ['get', '/admin/catalog/imports/runs', 'view'],
    'progress' => ['get', '/admin/catalog/imports/runs/{run}/progress', 'view'],
    'logs' => ['get', '/admin/catalog/imports/runs/{run}/logs', 'view'],
    'categories' => ['get', '/admin/catalog/imports/categories', 'view'],
    'store' => ['post', '/admin/catalog/imports', 'manage'],
    'preview' => ['get', '/admin/catalog/imports/preview', 'manage'],
    'pause' => ['patch', '/admin/catalog/imports/runs/{run}/pause', 'manage'],
    'resume' => ['patch', '/admin/catalog/imports/runs/{run}/resume', 'manage'],
    'cancel' => ['patch', '/admin/catalog/imports/runs/{run}/cancel', 'manage'],
    'save categories' => ['put', '/admin/catalog/imports/categories', 'manage'],
]);

it('redirects guests and forbids admins without the import permission', function (string $method, string $path, string $level): void {
    $run = CatalogImportRun::query()->create(['driver' => 'neozena', 'status' => 'running']);
    $url = $this->centralUrl(str_replace('{run}', (string) $run->id, $path));

    $this->{$method}($url)->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(catalogAdmin([]), 'admin')->{$method.'Json'}($url)->assertForbidden();

    if ($level === 'manage') {
        $this->actingAs(catalogAdmin(['catalog.imports.view']), 'admin')->{$method.'Json'}($url)->assertForbidden();
    }
})->with('import endpoints');

it('shows the not-configured state with a link to Integrations once the base URL is cleared', function (): void {
    configureNeozena('');

    $this->actingAs(catalogAdmin(), 'admin')
        ->get($this->centralUrl('/admin/catalog/imports'))
        ->assertOk()
        ->assertSee(__('catalog::imports.not_configured.title'))
        ->assertSee(route('admin.integrations.show', 'neozena'), false);

    $this->postJson($this->centralUrl('/admin/catalog/imports'), ['driver' => 'neozena'])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('catalog::imports.errors.not_configured'));
});

it('works out of the box: the never-imported state with the default public API and no saved settings', function (): void {
    $this->actingAs(catalogAdmin(['catalog.imports.view']), 'admin')
        ->get($this->centralUrl('/admin/catalog/imports'))
        ->assertOk()
        ->assertSee(__('catalog::imports.never.title'))
        ->assertSee('neozena.com')
        ->assertDontSee(__('catalog::imports.not_configured.title'));
});

it('shows the never-imported state once the driver is configured', function (): void {
    configureNeozena();

    $this->actingAs(catalogAdmin(['catalog.imports.view']), 'admin')
        ->get($this->centralUrl('/admin/catalog/imports'))
        ->assertOk()
        ->assertSee(__('catalog::imports.never.title'))
        ->assertSee('neozena.test')
        ->assertDontSee(__('catalog::imports.modal.submit'));
});

it('validates the run import request', function (): void {
    configureNeozena();
    $this->actingAs(catalogAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/catalog/imports'), [])->assertUnprocessable()->assertJsonValidationErrors(['driver']);
    $this->postJson($this->centralUrl('/admin/catalog/imports'), ['driver' => 'shopify', 'max_pages' => 0, 'update_existing' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['driver', 'max_pages', 'update_existing']);
    $this->getJson($this->centralUrl('/admin/catalog/imports/preview?driver=shopify'))->assertUnprocessable()->assertJsonValidationErrors(['driver']);
    $this->putJson($this->centralUrl('/admin/catalog/imports/categories'), ['driver' => 'neozena', 'mappings' => ['1' => 999999]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mappings.1']);
});

it('previews the first page of the source and collects its categories', function (): void {
    configureNeozena();
    fakeNeozena([[neozenaProduct(1), ['id' => 'bad']]]);

    $json = $this->actingAs(catalogAdmin(), 'admin')
        ->getJson($this->centralUrl('/admin/catalog/imports/preview?driver=neozena'))
        ->assertOk()
        ->json('data');

    expect($json['total'])->toBe(2)
        ->and($json['invalid'])->toBe(1)
        ->and($json['items'][0])->toMatchArray(['sku' => 'NZ-1', 'name' => 'Linen Shirt 1', 'variants' => 2, 'images' => 2])
        ->and($json['html'])->toContain('Linen Shirt 1')
        ->and(CatalogImportCategoryMap::query()->where('external_id', 501)->whereNull('category_id')->exists())->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('answers a readable error when the source fails', function (): void {
    configureNeozena();
    Http::fake([NEOZENA_BASE.'/*' => Http::response('down', 503)]);

    $this->actingAs(catalogAdmin(), 'admin')
        ->getJson($this->centralUrl('/admin/catalog/imports/preview'))
        ->assertUnprocessable()
        ->assertJsonPath('message', __('catalog::imports.errors.http', ['status' => 503, 'page' => 1]));
});

it('imports products with variants, images, stock and categories through the batch', function (): void {
    configureNeozena();
    $category = mapNeozenaCategory();
    $branch = Branch::factory()->create();
    fakeNeozena([[neozenaProduct(1)], [neozenaProduct(2, ['variants' => [], 'variant_combinations' => [], 'stock' => 9])]]);
    $admin = catalogAdmin();
    $this->actingAs($admin, 'admin');

    $run = runImport();

    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->total_pages)->toBe(2)
        ->and($run->only(['total_items', 'processed_count', 'created_count', 'updated_count', 'skipped_count', 'error_count', 'images_total', 'images_done']))
        ->toBe(['total_items' => 2, 'processed_count' => 2, 'created_count' => 2, 'updated_count' => 0, 'skipped_count' => 0, 'error_count' => 0, 'images_total' => 4, 'images_done' => 4]);

    $product = Product::query()->where('external_source', 'neozena')->where('external_id', 1)->firstOrFail();
    expect($product->sku)->toBe('NZ-1')
        ->and($product->getTranslation('name', 'ar'))->toBe('قميص كتان 1')
        ->and($product->base_price->minor)->toBe(2400)
        ->and($product->status->value)->toBe('published')
        ->and($product->getTranslation('meta_keywords', 'en'))->toBe('linen,shirt')
        ->and($product->categories->pluck('id')->all())->toBe([$category->id])
        ->and($product->variants()->count())->toBe(2)
        ->and($product->media()->count())->toBe(2)
        ->and($product->stock)->toBe(10);

    expect(ProductVariant::query()->where('sku', 'NZ-1-L')->value('price'))->not->toBeNull()
        ->and(Attribute::query()->where('external_source', 'neozena')->where('external_id', 70)->exists())->toBeTrue()
        ->and(AttributeOption::query()->where('external_source', 'neozena')->count())->toBe(2)
        ->and(BranchStock::query()->where('branch_id', $branch->id)->where('product_id', $product->id)->sum('on_hand'))->toEqual(10);

    $simple = Product::query()->where('external_id', 2)->firstOrFail();
    expect($simple->variants()->count())->toBe(0)->and($simple->stock)->toBe(9);

    Queue::assertPushed(ConvertCatalogMedia::class, 4);
    Notification::assertSentTo($admin, CatalogImportFinishedNotification::class, fn (CatalogImportFinishedNotification $notification): bool => $notification->status === 'completed' && $notification->created === 2);

    $this->getJson($this->centralUrl("/admin/catalog/imports/runs/{$run->id}/progress"))
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.products.percent', 100)
        ->assertJsonPath('data.counts.created', 2);

    $log = $this->getJson($this->centralUrl("/admin/catalog/imports/runs/{$run->id}/logs?draw=1&start=0&length=10"))->assertOk()->json();
    expect($log['recordsTotal'])->toBe(2)->and($log['data'][0]['result'])->toContain(__('catalog::imports.results.created'));

    $this->get($this->centralUrl('/admin/catalog/imports'))->assertOk()->assertSee(__('catalog::imports.progress.completed'))->assertSee('2 / 2');
    expect($this->getJson($this->centralUrl('/admin/catalog/imports/runs?draw=1&start=0&length=5'))->json('recordsTotal'))->toBe(1);
});

it('is idempotent: re-running skips unchanged items and updates changed ones without duplicates', function (): void {
    configureNeozena();
    mapNeozenaCategory();
    Branch::factory()->create();
    fakeNeozena([[neozenaProduct(1), neozenaProduct(2)]]);
    $this->actingAs(catalogAdmin(), 'admin');

    runImport();
    $counts = fn (): array => [Product::query()->count(), ProductVariant::query()->count(), CatalogMedia::query()->count(), Attribute::query()->count(), AttributeOption::query()->count()];
    $before = $counts();

    $second = runImport();
    expect($second->only(['created_count', 'updated_count', 'skipped_count', 'error_count']))->toBe(['created_count' => 0, 'updated_count' => 0, 'skipped_count' => 2, 'error_count' => 0])
        ->and($counts())->toBe($before)
        ->and(CatalogImportLog::query()->where('run_id', $second->id)->value('message'))->toBe(__('catalog::imports.messages.unchanged'));

    fakeNeozena([[neozenaProduct(1, ['current_price' => '22.50']), neozenaProduct(2)]]);
    $third = runImport();

    expect($third->only(['created_count', 'updated_count', 'skipped_count']))->toBe(['created_count' => 0, 'updated_count' => 1, 'skipped_count' => 1])
        ->and($counts())->toBe($before)
        ->and(Product::query()->where('external_id', 1)->first()->base_price->minor)->toBe(2250)
        ->and(CatalogImportLog::query()->where('run_id', $third->id)->where('result', 'updated')->value('message'))->toContain('$22.50');

    $noUpdates = runImport(['update_existing' => false]);
    expect($noUpdates->skipped_count)->toBe(2)->and($counts())->toBe($before);
});

it('adopts an existing product with the same SKU instead of duplicating it', function (): void {
    configureNeozena();
    mapNeozenaCategory();
    $existing = Product::factory()->create(['sku' => 'NZ-1']);
    fakeNeozena([[neozenaProduct(1)]]);
    $this->actingAs(catalogAdmin(), 'admin');

    $run = runImport();

    expect($run->updated_count)->toBe(1)
        ->and(Product::query()->count())->toBe(1)
        ->and($existing->refresh()->external_id)->toBe(1);
});

it('logs a bad item as an error and keeps importing the rest', function (): void {
    configureNeozena();
    mapNeozenaCategory();
    fakeNeozena([[
        neozenaProduct(1),
        ['id' => 2, 'sku' => 'NZ-2', 'translations' => [], 'current_price' => '5'],
        neozenaProduct(3, ['current_price' => '-4']),
        neozenaProduct(4),
    ]]);
    $this->actingAs(catalogAdmin(), 'admin');

    $run = runImport();

    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->created_count)->toBe(2)
        ->and($run->error_count)->toBe(2)
        ->and(Product::query()->whereIn('external_id', [1, 4])->count())->toBe(2);

    $errors = $this->getJson($this->centralUrl("/admin/catalog/imports/runs/{$run->id}/logs?draw=1&start=0&length=10&result=error"))->json();
    expect($errors['recordsFiltered'])->toBe(2)
        ->and(collect($errors['data'])->pluck('message')->implode(' '))->toContain(__('catalog::imports.errors.missing_name'))->toContain(__('catalog::imports.errors.negative_price'));
});

it('skips products of unmapped categories with a log line when automatic creation is off', function (): void {
    configureNeozena();
    fakeNeozena([[neozenaProduct(1)]]);
    $this->actingAs(catalogAdmin(), 'admin');

    $run = runImport(['auto_create' => '0']);

    expect($run->skipped_count)->toBe(1)
        ->and(Product::query()->count())->toBe(0)
        ->and(CatalogImportLog::query()->where('run_id', $run->id)->value('message'))->toBe(__('catalog::imports.messages.category_unmapped', ['name' => 'Shirts']));

    $this->get($this->centralUrl('/admin/catalog/imports'))->assertSee(trans_choice('catalog::imports.unmapped.message', 1, ['count' => 1]));
});

it('fails the run when the source cannot be read', function (): void {
    configureNeozena();
    Http::fake([NEOZENA_BASE.'/*' => Http::response('nope', 500)]);
    $this->actingAs(catalogAdmin(), 'admin');

    $run = runImport();

    expect($run->status)->toBe(ImportStatus::Failed)
        ->and($run->failure_message)->toBe(__('catalog::imports.errors.http', ['status' => 500, 'page' => 1]));
});

it('refuses a second run while one is active, and pauses, resumes and cancels it', function (): void {
    configureNeozena();
    Bus::fake();
    $this->actingAs(catalogAdmin(), 'admin');
    $run = CatalogImportRun::query()->create(['driver' => 'neozena', 'status' => 'running', 'total_items' => 10, 'total_pages' => 2]);

    $this->postJson($this->centralUrl('/admin/catalog/imports'), ['driver' => 'neozena'])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('catalog::imports.errors.already_running'));

    $this->get($this->centralUrl('/admin/catalog/imports'))
        ->assertOk()
        ->assertSee(__('catalog::imports.progress.running'))
        ->assertSee(route('admin.catalog.imports.pause', $run), false)
        ->assertSee(route('admin.catalog.imports.progress', $run), false);

    $this->patchJson($this->centralUrl("/admin/catalog/imports/runs/{$run->id}/resume"))->assertUnprocessable();
    $this->patchJson($this->centralUrl("/admin/catalog/imports/runs/{$run->id}/pause"))->assertOk()->assertJsonPath('data.status', 'paused');
    $this->patchJson($this->centralUrl("/admin/catalog/imports/runs/{$run->id}/resume"))->assertOk()->assertJsonPath('data.status', 'running');
    $this->patchJson($this->centralUrl("/admin/catalog/imports/runs/{$run->id}/cancel"))->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.active', false);
    $this->patchJson($this->centralUrl("/admin/catalog/imports/runs/{$run->id}/cancel"))->assertUnprocessable();

    expect($run->refresh()->finished_at)->not->toBeNull();
    // The paused run had no live batch: its two unfinished pages were queued again.
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2);
});

it('queues one ImportCatalogPage job per page in one batch', function (): void {
    configureNeozena();
    fakeNeozena([[neozenaProduct(1)], [neozenaProduct(2)], [neozenaProduct(3)]]);
    Bus::fake([ImportCatalogPage::class]);
    $this->actingAs(catalogAdmin(), 'admin');

    $run = runImport(['max_pages' => 2]);

    expect($run->status)->toBe(ImportStatus::Running)->and($run->total_pages)->toBe(2)->and($run->batch_id)->not->toBeNull();
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->map(fn (ImportCatalogPage $job): int => $job->page)->all() === [1, 2]
        && $batch->jobs->every(fn (ImportCatalogPage $job): bool => $job->runId === $run->id));
});

it('creates a missing category from Neozena by default and imports its products', function (): void {
    configureNeozena();
    fakeNeozena([[neozenaProduct(1)]]);
    $this->actingAs(catalogAdmin(), 'admin');

    $run = runImport();
    $category = Category::query()->where('external_source', 'neozena')->where('external_id', 501)->sole();

    expect($run->created_count)->toBe(1)
        ->and($category->getTranslation('name', 'en'))->toBe('Shirts')
        ->and($category->getTranslation('name', 'ar'))->toBe('قمصان')
        ->and(CatalogImportCategoryMap::query()->where('external_id', 501)->value('category_id'))->toBe($category->id)
        ->and(Product::query()->sole()->categories->pluck('id')->all())->toBe([$category->id]);
});

it('stops importing items once the run is cancelled', function (): void {
    configureNeozena();
    mapNeozenaCategory();
    fakeNeozena([[neozenaProduct(1)], [neozenaProduct(2)]]);
    $this->actingAs(catalogAdmin(), 'admin');
    $run = CatalogImportRun::query()->create(['driver' => 'neozena', 'status' => 'cancelled']);

    expect(app(CatalogImportService::class)->importPage($run->id, 1))->toBeTrue()
        ->and(Product::query()->count())->toBe(0);

    $run->update(['status' => 'paused']);
    expect(app(CatalogImportService::class)->importPage($run->id, 1))->toBeFalse();
});

it('maps source categories', function (): void {
    configureNeozena();
    $category = Category::factory()->create();
    $map = CatalogImportCategoryMap::query()->create(['driver' => 'neozena', 'external_id' => 501, 'name' => ['en' => 'Shirts']]);
    $this->actingAs(catalogAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/catalog/imports/categories?unmapped=1'))->assertOk()->assertSee('Shirts');

    $this->putJson($this->centralUrl('/admin/catalog/imports/categories'), ['driver' => 'neozena', 'mappings' => [$map->id => $category->id]])
        ->assertOk()
        ->assertJsonPath('message', __('catalog::imports.mapping.saved'));

    expect($map->refresh()->category_id)->toBe($category->id);

    $this->get($this->centralUrl('/admin/catalog/imports/categories?unmapped=1'))->assertOk()->assertSee(__('catalog::imports.mapping.all_mapped_title'));

    $this->putJson($this->centralUrl('/admin/catalog/imports/categories'), ['driver' => 'neozena', 'mappings' => [$map->id + 999 => null]])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('catalog::imports.mapping.stale'));
});

it('tests the Neozena integration card connection', function (): void {
    configureNeozena();
    fakeNeozena([[neozenaProduct(1)]]);

    $this->actingAs(catalogAdmin(), 'admin')
        ->postJson($this->centralUrl('/admin/integrations/neozena/test'))
        ->assertOk()
        ->assertJsonPath('data.status', 'connected');
});
