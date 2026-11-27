<?php

use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Catalog\Jobs\ConvertCatalogMedia;
use App\Modules\Catalog\Models\CatalogImportCategoryMap;
use App\Modules\Catalog\Models\CatalogImportLog;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\Imports\CatalogImportService;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Inventory\Models\Branch;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../CatalogHelpers.php';
require_once __DIR__.'/../../Integrations/IntegrationsHelpers.php';

/*
| `catalog:import-neozena`: the CLI front of CatalogImportService over a faked Neozena
| API (same shapes as the admin import tests): credentials check, dry run, inline /
| queued runs, page window, idempotent re-runs, resume and the DB-controlled schedule.
*/

const NEOZENA_CMD_BASE = 'https://neozena-cli.test/api/catalog';

/**
 * @param  array<string, mixed>  $settings
 */
function neozenaCmdConfigure(array $settings = []): void
{
    app(SettingsRepositoryContract::class)->put('neozena', ['enabled' => true, 'base_url' => NEOZENA_CMD_BASE, 'api_token' => 'cli-token', ...$settings], ['api_token']);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function neozenaCmdProduct(int $id, array $overrides = []): array
{
    return array_replace([
        'id' => $id,
        'sku' => 'NZC-'.$id,
        'status' => true,
        'current_price' => '24.00',
        'weight' => 350,
        'stock' => null,
        'translations' => [['locale' => 'en', 'title' => 'Cli Shirt '.$id], ['locale' => 'ar', 'title' => 'قميص '.$id]],
        'categories' => [['id' => 601, 'translations' => [['locale' => 'en', 'name' => 'Shirts']]]],
        'variants' => [[
            'id' => 80,
            'translations' => [['locale' => 'en', 'name' => 'Size']],
            'options' => [['id' => 801, 'value' => 'M'], ['id' => 802, 'value' => 'L']],
        ]],
        'variant_combinations' => [
            ['id' => $id * 10 + 1, 'sku' => 'NZC-'.$id.'-M', 'price' => '24.00', 'stock' => 4, 'option_ids' => [801]],
            ['id' => $id * 10 + 2, 'sku' => 'NZC-'.$id.'-L', 'price' => '26.50', 'stock' => 6, 'option_ids' => [802]],
        ],
        'images' => [
            ['id' => $id * 100 + 1, 'image_path' => 'https://cdn.neozena-cli.test/'.$id.'-1.png', 'is_primary' => true],
            ['id' => $id * 100 + 2, 'image_path' => 'https://cdn.neozena-cli.test/'.$id.'-2.png'],
        ],
    ], $overrides);
}

/**
 * Serves the given pages (product rows per page); a later call swaps the pages.
 *
 * @param  list<list<array<string, mixed>>>  $pages
 */
function neozenaCmdFake(array $pages): void
{
    $fresh = ! isset(test()->neozenaCmdPages);
    test()->neozenaCmdPages = $pages;

    if (! $fresh) {
        return;
    }

    $png = UploadedFile::fake()->image('p.png', 8, 8)->getContent();

    Http::fake(function (Request $request) use ($png) {
        if (str_starts_with($request->url(), 'https://cdn.neozena-cli.test/')) {
            return Http::response($png, 200, ['Content-Type' => 'image/png']);
        }

        if (str_starts_with($request->url(), NEOZENA_CMD_BASE.'/products')) {
            $pages = test()->neozenaCmdPages;
            $page = (int) ($request->data()['page'] ?? 1);

            return Http::response(['data' => $pages[$page - 1] ?? [], 'meta' => ['last_page' => count($pages), 'total' => array_sum(array_map('count', $pages))]]);
        }

        return Http::response('', 404);
    });
}

function neozenaCmdMapCategory(): Category
{
    $category = Category::factory()->create();
    CatalogImportCategoryMap::query()->create(['driver' => 'neozena', 'external_id' => 601, 'name' => ['en' => 'Shirts'], 'category_id' => $category->id]);

    return $category;
}

/**
 * @param  array<string, mixed>  $options
 */
function neozenaCmdInlineRun(ImportStatus $status, int $pagesDone, array $options = []): CatalogImportRun
{
    return CatalogImportRun::query()->create([
        'driver' => 'neozena',
        'status' => $status,
        'options' => ['update_existing' => false, 'max_pages' => null, 'from_page' => 1, 'mode' => CatalogImportRun::MODE_INLINE, ...$options],
        'total_pages' => 2,
        'pages_done' => $pagesDone,
        'total_items' => 2,
        'processed_count' => $pagesDone,
        'created_count' => $pagesDone,
        'started_at' => now()->subHour(),
    ]);
}

/**
 * @return list<Event>
 */
function neozenaCmdScheduled(): array
{
    app()->forgetInstance(Schedule::class);

    return array_values(array_filter(app(Schedule::class)->events(), static fn (Event $event): bool => $event->description === 'catalog:import-neozena'));
}

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake([ConvertCatalogMedia::class]);
    Notification::fake();
});

it('refuses with a clear message while Neozena credentials are not configured', function (): void {
    $this->artisan('catalog:import-neozena', ['--force' => true, '--sync' => true])
        ->expectsOutputToContain('Neozena is not configured')
        ->assertFailed();

    neozenaCmdConfigure(['enabled' => false]);

    $this->artisan('catalog:import-neozena', ['--dry-run' => true])->expectsOutputToContain('Neozena is not configured')->assertFailed();

    expect(CatalogImportRun::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('rejects option values that are not positive whole numbers', function (string $option): void {
    neozenaCmdConfigure();

    $this->artisan('catalog:import-neozena', [$option => '0', '--force' => true])
        ->expectsOutputToContain($option.' must be a positive whole number')
        ->assertExitCode(2);
})->with(['--pages', '--from-page', '--resume']);

it('previews counts on a dry run and writes nothing', function (): void {
    neozenaCmdConfigure();
    neozenaCmdFake([[neozenaCmdProduct(1), ['id' => 'bad']], [neozenaCmdProduct(2)]]);

    $this->artisan('catalog:import-neozena', ['--dry-run' => true, '--pages' => '5'])
        ->expectsOutputToContain('Dry run: nothing is written.')
        ->expectsTable(['', ''], [
            ['Endpoint', 'neozena-cli.test'],
            ['Products in the source', 3],
            ['Source pages', 2],
            ['Pages to import', '2 (page 1 to 2)'],
            ['Unreadable items on page 1', 1],
            ['Mapped categories', '0 / 0'],
            ['Unmapped categories (products skipped)', 0],
            ['Existing products', 'skipped'],
        ])
        ->expectsOutputToContain('Cli Shirt 1')
        ->assertSuccessful();

    expect(CatalogImportRun::query()->count())->toBe(0)
        ->and(CatalogImportCategoryMap::query()->count())->toBe(0)
        ->and(CatalogImportLog::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0);
});

it('imports products with variants and images inline into the central catalog', function (): void {
    neozenaCmdConfigure();
    $category = neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)], [neozenaCmdProduct(2)]]);

    $this->artisan('catalog:import-neozena', ['--sync' => true, '--force' => true])
        ->expectsOutputToContain('started (every page from page 1, inline)')
        ->expectsOutputToContain('2 / 2')
        ->assertSuccessful();

    $run = CatalogImportRun::query()->sole();
    $product = Product::query()->where('external_source', 'neozena')->where('external_id', 1)->sole();

    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->isInline())->toBeTrue()
        ->and($run->started_by)->toBeNull()
        ->and($product->sku)->toBe('NZC-1')
        ->and($product->categories->pluck('id')->all())->toBe([$category->id])
        ->and($product->variants()->count())->toBe(2)
        ->and($product->media()->count())->toBe(2)
        ->and(Product::query()->count())->toBe(2);
});

it('imports only the requested page window', function (): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)], [neozenaCmdProduct(2)], [neozenaCmdProduct(3)]]);

    $this->artisan('catalog:import-neozena', ['--sync' => true, '--force' => true, '--from-page' => '2', '--pages' => '1'])->assertSuccessful();

    $run = CatalogImportRun::query()->sole();
    expect(Product::query()->pluck('external_id')->all())->toBe([2])
        ->and($run->total_pages)->toBe(1)
        ->and($run->fromPage())->toBe(2);

    $this->artisan('catalog:import-neozena', ['--sync' => true, '--force' => true, '--from-page' => '9'])
        ->expectsOutputToContain(__('catalog::imports.errors.page_out_of_range', ['page' => 9, 'last' => 3]))
        ->assertFailed();
});

it('updates on a re-run with --update-existing instead of duplicating, and skips without it', function (): void {
    neozenaCmdConfigure();
    Branch::factory()->create();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)]]);

    $this->artisan('catalog:import-neozena', ['--sync' => true, '--force' => true])->assertSuccessful();

    neozenaCmdFake([[neozenaCmdProduct(1, ['current_price' => '30.00', 'variant_combinations' => [
        ['id' => 11, 'sku' => 'NZC-1-M', 'price' => '30.00', 'stock' => 9, 'option_ids' => [801]],
        ['id' => 12, 'sku' => 'NZC-1-L', 'price' => '31.00', 'stock' => 2, 'option_ids' => [802]],
    ]])]]);

    $this->artisan('catalog:import-neozena', ['--sync' => true, '--force' => true])->assertSuccessful();
    $skipped = CatalogImportRun::query()->latest('id')->first();
    expect($skipped->skipped_count)->toBe(1)->and(Product::query()->sole()->base_price->minor)->toBe(2400);

    $this->artisan('catalog:import-neozena', ['--sync' => true, '--force' => true, '--update-existing' => true])->assertSuccessful();
    $updated = CatalogImportRun::query()->latest('id')->first();
    $product = Product::query()->sole();

    expect($updated->updated_count)->toBe(1)
        ->and($updated->created_count)->toBe(0)
        ->and($product->base_price->minor)->toBe(3000)
        ->and($product->stock)->toBe(11)
        ->and(ProductVariant::query()->count())->toBe(2)
        ->and($product->media()->count())->toBe(2);
});

it('queues the run by default', function (): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)]]);

    // The test queue is sync, so the queued batch has already finished here.
    $this->artisan('catalog:import-neozena', ['--force' => true])
        ->expectsOutputToContain(', queued).')
        ->assertSuccessful();

    $run = CatalogImportRun::query()->sole();
    expect($run->isInline())->toBeFalse()
        ->and($run->status)->toBe(ImportStatus::Completed)
        ->and(Product::query()->count())->toBe(1);
});

it('asks for confirmation unless forced', function (): void {
    neozenaCmdConfigure();

    $this->artisan('catalog:import-neozena', ['--sync' => true])
        ->expectsConfirmation('Import every page from page 1 of neozena-cli.test into the central catalog?', 'no')
        ->expectsOutputToContain('Import not started.')
        ->assertFailed();

    expect(CatalogImportRun::query()->count())->toBe(0);
});

it('prints item failures and exits non-zero', function (): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1), ['id' => 'bad', 'sku' => 'BROKEN']]]);

    $this->artisan('catalog:import-neozena', ['--sync' => true, '--force' => true])
        ->expectsOutputToContain('Failures:')
        ->expectsOutputToContain('BROKEN: '.__('catalog::imports.errors.missing_id'))
        ->assertFailed();

    expect(CatalogImportRun::query()->sole()->only(['created_count', 'error_count']))->toBe(['created_count' => 1, 'error_count' => 1]);
});

it('exits non-zero when the source cannot be read', function (): void {
    neozenaCmdConfigure();
    Http::fake([NEOZENA_CMD_BASE.'/*' => Http::response('down', 503)]);

    $this->artisan('catalog:import-neozena', ['--sync' => true, '--force' => true])
        ->expectsOutputToContain(__('catalog::imports.errors.http', ['status' => 503, 'page' => 1]))
        ->assertFailed();

    expect(CatalogImportRun::query()->sole()->status)->toBe(ImportStatus::Failed);
});

it('resumes an interrupted inline run from its next page', function (ImportStatus $status): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)], [neozenaCmdProduct(2)]]);
    $run = neozenaCmdInlineRun($status, 1);
    $run->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    $this->artisan('catalog:import-neozena', ['--resume' => (string) $run->id])
        ->expectsOutputToContain('continues from page 2')
        ->assertSuccessful();

    $run->refresh();
    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->pages_done)->toBe(2)
        ->and($run->created_count)->toBe(2)
        ->and(Product::query()->pluck('external_id')->all())->toBe([2]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/products') && (int) ($request->data()['page'] ?? 0) === 1);
})->with([
    'paused' => ImportStatus::Paused,
    'process died' => ImportStatus::Running,
    'failed' => ImportStatus::Failed,
]);

it('refuses to resume runs that cannot continue', function (): void {
    neozenaCmdConfigure();

    $this->artisan('catalog:import-neozena', ['--resume' => '999999'])->expectsOutputToContain('does not exist')->assertFailed();

    $live = neozenaCmdInlineRun(ImportStatus::Running, 1);
    $this->artisan('catalog:import-neozena', ['--resume' => (string) $live->id])
        ->expectsOutputToContain(__('catalog::imports.errors.still_running'))
        ->assertFailed();

    $live->update(['status' => ImportStatus::Completed]);
    $this->artisan('catalog:import-neozena', ['--resume' => (string) $live->id])
        ->expectsOutputToContain(__('catalog::imports.errors.already_completed'))
        ->assertFailed();
});

it('resumes a paused queued run on the queue and keeps CLI runs out of the admin resume', function (): void {
    neozenaCmdConfigure();
    $queued = neozenaCmdInlineRun(ImportStatus::Paused, 1, ['mode' => CatalogImportRun::MODE_QUEUE]);

    $this->artisan('catalog:import-neozena', ['--resume' => (string) $queued->id])
        ->expectsOutputToContain('its queued pages continue')
        ->assertSuccessful();
    expect($queued->refresh()->status)->toBe(ImportStatus::Running);

    $inline = neozenaCmdInlineRun(ImportStatus::Paused, 1);
    expect(app(CatalogImportService::class)->progress($inline)['can_resume'])->toBeFalse()
        ->and(fn () => app(CatalogImportService::class)->resume($inline))
        ->toThrow(DomainException::class, __('catalog::imports.errors.resume_from_cli', ['id' => $inline->id]));
});

it('registers the daily --update-existing sync only when enabled in the Neozena settings', function (): void {
    expect(neozenaCmdScheduled())->toBe([]);

    neozenaCmdConfigure(['daily_sync' => false, 'daily_sync_at' => '04:15']);
    expect(neozenaCmdScheduled())->toBe([]);

    neozenaCmdConfigure(['enabled' => false, 'daily_sync' => true, 'daily_sync_at' => '04:15']);
    expect(neozenaCmdScheduled())->toBe([]);

    neozenaCmdConfigure(['daily_sync' => true, 'daily_sync_at' => '04:15']);
    $events = neozenaCmdScheduled();

    expect($events)->toHaveCount(1)
        ->and($events[0]->expression)->toBe('15 4 * * *')
        ->and($events[0]->command)->toContain('catalog:import-neozena')
        ->and($events[0]->command)->toContain('--update-existing')
        ->and($events[0]->command)->toContain('--force');
});

it('saves the daily sync settings in the Neozena integration section', function (): void {
    $admin = integrationsAdmin('integrations.view', 'integrations.manage');
    $url = $this->centralUrl('/admin/integrations/neozena');

    $this->actingAs($admin, 'admin')
        ->putJson($url, ['enabled' => '1', 'base_url' => NEOZENA_CMD_BASE, 'daily_sync' => '1', 'daily_sync_at' => '25:99'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['daily_sync_at']);

    $this->putJson($url, ['enabled' => '1', 'base_url' => NEOZENA_CMD_BASE, 'daily_sync' => '1', 'daily_sync_at' => '02:45'])->assertOk();

    expect(app(SettingsRepositoryContract::class)->group('neozena'))->toMatchArray(['daily_sync' => true, 'daily_sync_at' => '02:45']);

    $this->get($url)->assertOk()->assertSee(__('integrations::admin.sections.neozena.fields.daily_sync'))->assertSee('type="time"', false);
});
