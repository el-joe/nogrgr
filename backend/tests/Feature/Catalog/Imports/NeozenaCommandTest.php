<?php

use App\Modules\Catalog\Enums\ImportStatus;
use App\Modules\Catalog\Enums\ProductStatus;
use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Jobs\ConvertCatalogMedia;
use App\Modules\Catalog\Jobs\ConvertCategoryThumbnail;
use App\Modules\Catalog\Jobs\Imports\ImportCatalogPage;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\CatalogImportCategoryMap;
use App\Modules\Catalog\Models\CatalogImportLog;
use App\Modules\Catalog\Models\CatalogImportRun;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Inventory\Models\Branch;
use App\Modules\Inventory\Services\BranchService;
use App\Modules\Localization\Models\Country;
use Illuminate\Bus\PendingBatch;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

require_once __DIR__.'/../CatalogHelpers.php';
require_once __DIR__.'/../Vendor/VendorCatalogHelpers.php';
require_once __DIR__.'/../../Integrations/IntegrationsHelpers.php';

/*
| `catalog:import-neozena`: the CLI front of CatalogImportService over a faked Neozena
| API (shapes of the old importer): works with no token / settings, dry run, one queued
| job per page + watching the run, page window, idempotent re-runs, automatic categories
| / attributes / variants, resume and the DB-controlled schedule.
*/

const NEOZENA_CMD_BASE = 'https://neozena-cli.test/api/catalog';

/**
 * @param  array<string, mixed>  $settings
 */
function neozenaCmdConfigure(array $settings = []): void
{
    app(SettingsRepositoryContract::class)->put('neozena', ['base_url' => NEOZENA_CMD_BASE, 'api_token' => 'cli-token', ...$settings], ['api_token']);
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
function neozenaCmdFake(array $pages, array $categories = [], array $variantGroups = []): void
{
    $fresh = ! isset(test()->neozenaCmdPages);
    test()->neozenaCmdPages = $pages;
    test()->neozenaCmdCategories = $categories;
    test()->neozenaCmdVariants = $variantGroups;

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

        if (preg_match('#^'.preg_quote(NEOZENA_CMD_BASE, '#').'/categories/(\d+)$#', $request->url(), $match) === 1 && isset(test()->neozenaCmdCategories[(int) $match[1]])) {
            return Http::response(['data' => test()->neozenaCmdCategories[(int) $match[1]]]);
        }

        if (str_starts_with($request->url(), NEOZENA_CMD_BASE.'/variants')) {
            return Http::response(['data' => test()->neozenaCmdVariants, 'meta' => ['last_page' => 1]]);
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
 * A started run of 2 pages (page 1 finished), as left behind by a lost worker / pause.
 */
function neozenaCmdStartedRun(ImportStatus $status, int $totalPages = 2, array $donePages = [1]): CatalogImportRun
{
    $run = CatalogImportRun::query()->create([
        'driver' => 'neozena',
        'status' => $status,
        'options' => ['update_existing' => false, 'max_pages' => null, 'from_page' => 1],
        'total_pages' => $totalPages,
        'pages_done' => count($donePages),
        'total_items' => $totalPages,
        'processed_count' => count($donePages),
        'created_count' => count($donePages),
        'started_at' => now()->subHour(),
    ]);

    foreach ($donePages as $page) {
        DB::connection($run->getConnectionName())->table('catalog_import_run_pages')->insert(['run_id' => $run->id, 'page' => $page, 'created_at' => now()]);
    }

    $run->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

    return $run;
}

/**
 * A product with two attributes (Color with swatches × Size) and four combinations,
 * each with its own SKU, price, stock, weight and thumbnail (old importer's shape).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function neozenaCmdVariantProduct(int $id, string $price = '30.00', array $overrides = []): array
{
    $combinations = [];
    $n = 0;

    foreach ([901, 902] as $color) {
        foreach ([911, 912] as $size) {
            $n++;
            $combinations[] = [
                'id' => $id * 10 + $n,
                'sku' => 'NZV-'.$id.'-'.$n,
                'price' => number_format((float) $price + $n, 2, '.', ''),
                'stock' => $n * 2,
                'weight' => 200 + $n,
                'is_active' => true,
                'thumbnail_path' => 'https://cdn.neozena-cli.test/v'.$id.'-'.$n.'.png',
                'option_ids' => [$color, $size],
            ];
        }
    }

    return neozenaCmdProduct($id, [
        'sku' => 'NZV-'.$id,
        'current_price' => $price,
        'variants' => [
            ['id' => 90, 'translations' => [['locale' => 'en', 'name' => 'Color'], ['locale' => 'ar', 'name' => 'اللون']], 'options' => [
                ['id' => 901, 'value' => 'Black', 'color' => '#000000', 'translations' => [['locale' => 'en', 'name' => 'Black'], ['locale' => 'ar', 'name' => 'أسود']]],
                ['id' => 902, 'value' => 'White', 'color' => '#FFFFFF', 'translations' => [['locale' => 'en', 'name' => 'White'], ['locale' => 'ar', 'name' => 'أبيض']]],
            ]],
            ['id' => 91, 'translations' => [['locale' => 'en', 'name' => 'Size'], ['locale' => 'ar', 'name' => 'المقاس']], 'options' => [
                ['id' => 911, 'value' => 'S', 'translations' => [['locale' => 'en', 'name' => 'S']]],
                ['id' => 912, 'value' => 'M', 'translations' => [['locale' => 'en', 'name' => 'M']]],
            ]],
        ],
        'variant_combinations' => $combinations,
        ...$overrides,
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
    Queue::fake([ConvertCatalogMedia::class, ConvertCategoryThumbnail::class]);
    Notification::fake();
});

it('works out of the box against the public API with no token and no saved settings', function (): void {
    neozenaCmdMapCategory();
    $png = UploadedFile::fake()->image('p.png', 8, 8)->getContent();
    Http::fake([
        'https://cdn.neozena-cli.test/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        'https://neozena.com/api/catalog/products*' => Http::response(['data' => [neozenaCmdProduct(1)], 'meta' => ['last_page' => 1, 'total' => 1]]),
        '*' => Http::response('', 404),
    ]);

    expect(app(SettingsRepositoryContract::class)->group('neozena'))->toBe([]);

    $this->artisan('catalog:import-neozena', ['--force' => true])
        ->expectsOutputToContain('queued (every page from page 1)')
        ->expectsOutputToContain('php artisan queue:work')
        ->assertSuccessful();

    expect(CatalogImportRun::query()->sole()->status)->toBe(ImportStatus::Completed)
        ->and(Product::query()->count())->toBe(1);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://neozena.com/api/catalog/products') && ! $request->hasHeader('Authorization'));
});

it('refuses with a clear message while the API base URL is cleared', function (): void {
    neozenaCmdConfigure(['base_url' => '']);

    $this->artisan('catalog:import-neozena', ['--force' => true])->expectsOutputToContain('Neozena has no API base URL')->assertFailed();
    $this->artisan('catalog:import-neozena', ['--dry-run' => true])->expectsOutputToContain('Neozena has no API base URL')->assertFailed();

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

it('queues one ImportCatalogPage job per page and exits with --no-wait', function (): void {
    neozenaCmdConfigure();
    neozenaCmdFake([[neozenaCmdProduct(1)], [neozenaCmdProduct(2)], [neozenaCmdProduct(3)]]);
    Bus::fake([ImportCatalogPage::class]);

    $this->artisan('catalog:import-neozena', ['--force' => true, '--no-wait' => true, '--from-page' => '2'])
        ->expectsOutputToContain('queued (every page from page 2)')
        ->expectsOutputToContain('--resume=')
        ->assertSuccessful();

    $run = CatalogImportRun::query()->sole();
    expect($run->status)->toBe(ImportStatus::Running)->and($run->total_pages)->toBe(2);
    Bus::assertBatchCount(1);
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->map(fn (ImportCatalogPage $job): array => [$job->runId, $job->page])->all() === [[$run->id, 2], [$run->id, 3]]);
    expect(Product::query()->count())->toBe(0);
});

it('watches the run row until the queue worker finishes it', function (): void {
    neozenaCmdConfigure();
    neozenaCmdFake([[neozenaCmdProduct(1)], [neozenaCmdProduct(2)]]);
    Bus::fake([ImportCatalogPage::class]);
    Sleep::fake();
    Sleep::whenFakingSleep(function (): void {
        // What the worker does meanwhile.
        CatalogImportRun::query()->update(['status' => ImportStatus::Completed, 'pages_done' => 2, 'created_count' => 2, 'processed_count' => 2, 'finished_at' => now()]);
    });

    $this->artisan('catalog:import-neozena', ['--force' => true])
        ->expectsOutputToContain('2 / 2')
        ->assertSuccessful();

    expect(CatalogImportRun::query()->sole()->status)->toBe(ImportStatus::Completed);

    Sleep::assertSleptTimes(1);
});

it('imports products with variants and images into the central catalog', function (): void {
    neozenaCmdConfigure();
    $category = neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)], [neozenaCmdProduct(2)]]);

    $this->artisan('catalog:import-neozena', ['--force' => true])
        ->expectsOutputToContain('2 / 2')
        ->assertSuccessful();

    $run = CatalogImportRun::query()->sole();
    $product = Product::query()->where('external_source', 'neozena')->where('external_id', 1)->sole();

    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->started_by)->toBeNull()
        ->and($run->batch_id)->not->toBeNull()
        ->and($product->sku)->toBe('NZC-1')
        ->and($product->categories->pluck('id')->all())->toBe([$category->id])
        ->and($product->variants()->count())->toBe(2)
        ->and($product->media()->count())->toBe(2)
        ->and(Product::query()->count())->toBe(2);
});

it('creates every attribute, option and variant once and updates them in place on a re-run', function (): void {
    neozenaCmdConfigure();
    Branch::factory()->create();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdVariantProduct(1)], [neozenaCmdVariantProduct(2, '40.00')]]);

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();

    $product = Product::query()->where('external_id', 1)->sole();
    $variants = $product->variants()->with('options')->orderBy('sku')->get();
    $color = Attribute::query()->where('external_source', 'neozena')->where('external_id', 90)->sole();

    expect(Attribute::query()->where('external_source', 'neozena')->count())->toBe(2)
        ->and(AttributeOption::query()->where('external_source', 'neozena')->count())->toBe(4)
        ->and($color->display_type->value)->toBe('color')
        ->and($color->getTranslation('name', 'ar'))->toBe('اللون')
        ->and(AttributeOption::query()->where('external_id', 902)->value('swatch'))->toBe('#ffffff')
        ->and(ProductVariant::query()->count())->toBe(8)
        ->and($variants->pluck('sku')->all())->toBe(['NZV-1-1', 'NZV-1-2', 'NZV-1-3', 'NZV-1-4'])
        ->and($variants->map(fn (ProductVariant $variant): int => $variant->price->minor)->all())->toBe([3100, 3200, 3300, 3400])
        ->and($variants->pluck('weight_grams')->all())->toBe([201, 202, 203, 204])
        ->and($variants->every(fn (ProductVariant $variant): bool => $variant->image_path !== null && $variant->options->count() === 2))->toBeTrue()
        ->and($product->stock)->toBe(2 + 4 + 6 + 8);

    $ids = ProductVariant::query()->orderBy('id')->pluck('id')->all();
    neozenaCmdFake([[neozenaCmdVariantProduct(1, '50.00')], [neozenaCmdVariantProduct(2, '40.00')]]);

    $this->artisan('catalog:import-neozena', ['--force' => true, '--update-existing' => true])->assertSuccessful();

    expect(ProductVariant::query()->orderBy('id')->pluck('id')->all())->toBe($ids)
        ->and(ProductVariant::query()->where('sku', 'NZV-1-1')->sole()->price->minor)->toBe(5100)
        ->and(Attribute::query()->where('external_source', 'neozena')->count())->toBe(2)
        ->and(AttributeOption::query()->where('external_source', 'neozena')->count())->toBe(4)
        ->and(CatalogImportRun::query()->latest('id')->first()->only(['updated_count', 'skipped_count']))->toBe(['updated_count' => 1, 'skipped_count' => 1]);
});

it('reuses attributes and options that already exist and reads unknown options from /variants', function (): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    $size = Attribute::query()->create(['name' => ['en' => 'Our size'], 'display_type' => 'text', 'status' => 'active', 'sort_order' => 1, 'external_source' => 'neozena', 'external_id' => 80]);
    $medium = $size->options()->create(['name' => ['en' => 'Medium'], 'is_active' => true, 'sort_order' => 1, 'external_source' => 'neozena', 'external_id' => 801]);
    neozenaCmdFake([[neozenaCmdProduct(1, ['variant_combinations' => [
        ['id' => 11, 'sku' => 'NZC-1-M', 'price' => '24.00', 'stock' => 4, 'option_ids' => [801, 921]],
        ['id' => 12, 'sku' => 'NZC-1-L', 'price' => '25.00', 'stock' => 1, 'option_ids' => [802, 921]],
    ]])]], variantGroups: [
        ['id' => 92, 'translations' => [['locale' => 'en', 'name' => 'Material']], 'options' => [['id' => 921, 'value' => 'Cotton', 'translations' => [['locale' => 'en', 'name' => 'Cotton']]]]],
    ]);

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();

    expect(ProductVariant::query()->where('sku', 'NZC-1-M')->sole()->options->pluck('id')->all())->toContain($medium->id)
        ->and($size->refresh()->getTranslation('name', 'en'))->toBe('Our size')
        ->and(Attribute::query()->where('external_id', 92)->sole()->getTranslation('name', 'en'))->toBe('Material')
        ->and(ProductVariant::query()->where('sku', 'NZC-1-L')->sole()->options()->count())->toBe(2)
        ->and(Attribute::query()->where('external_source', 'neozena')->count())->toBe(2);
});

it('creates missing categories with their parent chain and image, keeping manual mappings', function (): void {
    neozenaCmdConfigure();
    $manual = Category::factory()->create();
    CatalogImportCategoryMap::query()->create(['driver' => 'neozena', 'external_id' => 700, 'name' => ['en' => 'Sale'], 'category_id' => $manual->id]);
    neozenaCmdFake([[
        neozenaCmdProduct(1, ['categories' => [['id' => 601, 'translations' => [['locale' => 'en', 'name' => 'Hair Clips']]]]]),
        neozenaCmdProduct(2, ['categories' => [['id' => 601, 'translations' => [['locale' => 'en', 'name' => 'Hair Clips']]], ['id' => 700, 'translations' => [['locale' => 'en', 'name' => 'Sale']]]]]),
    ]], [
        601 => ['id' => 601, 'parent_id' => 600, 'thumbnail_path' => 'https://cdn.neozena-cli.test/c601.png', 'translations' => [['locale' => 'en', 'name' => "Women's Hair Clips", 'slug' => 'womens-hair-clips'], ['locale' => 'ar', 'name' => 'مشابك شعر']]],
        600 => ['id' => 600, 'parent_id' => null, 'translations' => [['locale' => 'en', 'name' => 'Accessories'], ['locale' => 'ar', 'name' => 'إكسسوارات']]],
    ]);

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();

    $child = Category::query()->where('external_source', 'neozena')->where('external_id', 601)->sole();
    $parent = Category::query()->where('external_source', 'neozena')->where('external_id', 600)->sole();

    expect($child->getTranslation('name', 'en'))->toBe("Women's Hair Clips")
        ->and($child->getTranslation('name', 'ar'))->toBe('مشابك شعر')
        ->and($child->slug)->toBe('womens-hair-clips')
        ->and($child->parent_id)->toBe($parent->id)
        ->and($child->status->value)->toBe('active')
        ->and($child->thumbnail_path)->not->toBeNull()
        ->and(Category::query()->where('external_source', 'neozena')->count())->toBe(2)
        ->and(CatalogImportCategoryMap::query()->where('external_id', 601)->value('category_id'))->toBe($child->id)
        ->and(CatalogImportCategoryMap::query()->where('external_id', 700)->value('category_id'))->toBe($manual->id)
        ->and(Product::query()->where('external_id', 2)->sole()->categories->pluck('id')->sort()->values()->all())->toBe(collect([$child->id, $manual->id])->sort()->values()->all())
        ->and(Product::query()->count())->toBe(2);
});

it('keeps skipping products of unmapped categories with --no-auto-create, and imports them on a later run', function (): void {
    neozenaCmdConfigure();
    neozenaCmdFake([[neozenaCmdProduct(1)]]);

    $this->artisan('catalog:import-neozena', ['--force' => true, '--no-auto-create' => true])->assertSuccessful();

    expect(CatalogImportRun::query()->sole()->skipped_count)->toBe(1)
        ->and(Product::query()->count())->toBe(0)
        ->and(Category::query()->where('external_source', 'neozena')->exists())->toBeFalse()
        ->and(CatalogImportLog::query()->value('message'))->toBe(__('catalog::imports.messages.category_unmapped', ['name' => 'Shirts']));

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();

    expect(CatalogImportRun::query()->latest('id')->first()->created_count)->toBe(1)
        ->and(Product::query()->sole()->categories()->count())->toBe(1);
});

it('imports only the requested page window', function (): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)], [neozenaCmdProduct(2)], [neozenaCmdProduct(3)]]);

    $this->artisan('catalog:import-neozena', ['--force' => true, '--from-page' => '2', '--pages' => '1'])->assertSuccessful();

    $run = CatalogImportRun::query()->sole();
    expect(Product::query()->pluck('external_id')->all())->toBe([2])
        ->and($run->total_pages)->toBe(1)
        ->and($run->fromPage())->toBe(2);

    $this->artisan('catalog:import-neozena', ['--force' => true, '--from-page' => '9'])
        ->expectsOutputToContain(__('catalog::imports.errors.page_out_of_range', ['page' => 9, 'last' => 3]))
        ->assertFailed();
});

it('updates on a re-run with --update-existing instead of duplicating, and skips without it', function (): void {
    neozenaCmdConfigure();
    Branch::factory()->create();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)]]);

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();

    neozenaCmdFake([[neozenaCmdProduct(1, ['current_price' => '30.00', 'variant_combinations' => [
        ['id' => 11, 'sku' => 'NZC-1-M', 'price' => '30.00', 'stock' => 9, 'option_ids' => [801]],
        ['id' => 12, 'sku' => 'NZC-1-L', 'price' => '31.00', 'stock' => 2, 'option_ids' => [802]],
    ]])]]);

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();
    $skipped = CatalogImportRun::query()->latest('id')->first();
    expect($skipped->skipped_count)->toBe(1)->and(Product::query()->sole()->base_price->minor)->toBe(2400);

    $this->artisan('catalog:import-neozena', ['--force' => true, '--update-existing' => true])->assertSuccessful();
    $updated = CatalogImportRun::query()->latest('id')->first();
    $product = Product::query()->sole();

    expect($updated->updated_count)->toBe(1)
        ->and($updated->created_count)->toBe(0)
        ->and($product->base_price->minor)->toBe(3000)
        ->and($product->stock)->toBe(11)
        ->and(ProductVariant::query()->count())->toBe(2)
        ->and($product->media()->count())->toBe(2);
});

it('asks for confirmation unless forced', function (): void {
    neozenaCmdConfigure();

    $this->artisan('catalog:import-neozena')
        ->expectsConfirmation('Import every page from page 1 of neozena-cli.test into the central catalog?', 'no')
        ->expectsOutputToContain('Import not started.')
        ->assertFailed();

    expect(CatalogImportRun::query()->count())->toBe(0);
});

it('prints item failures and exits non-zero', function (): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1), ['id' => 'bad', 'sku' => 'BROKEN']]]);

    $this->artisan('catalog:import-neozena', ['--force' => true])
        ->expectsOutputToContain('Failures:')
        ->expectsOutputToContain('BROKEN: '.__('catalog::imports.errors.missing_id'))
        ->assertFailed();

    expect(CatalogImportRun::query()->sole()->only(['created_count', 'error_count']))->toBe(['created_count' => 1, 'error_count' => 1]);
});

it('exits non-zero when the source cannot be read', function (): void {
    neozenaCmdConfigure();
    Http::fake([NEOZENA_CMD_BASE.'/*' => Http::response('down', 503)]);

    $this->artisan('catalog:import-neozena', ['--force' => true])
        ->expectsOutputToContain(__('catalog::imports.errors.http', ['status' => 503, 'page' => 1]))
        ->assertFailed();

    expect(CatalogImportRun::query()->sole()->status)->toBe(ImportStatus::Failed);
});

it('resumes an interrupted run by queuing only its unfinished pages again', function (ImportStatus $status): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)], [neozenaCmdProduct(2)]]);
    $run = neozenaCmdStartedRun($status);

    $this->artisan('catalog:import-neozena', ['--resume' => (string) $run->id])
        ->expectsOutputToContain('resumed on the queue (1 page(s) left)')
        ->assertSuccessful();

    $run->refresh();
    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->pages_done)->toBe(2)
        ->and($run->created_count)->toBe(2)
        ->and(Product::query()->pluck('external_id')->all())->toBe([2]);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/products') && (int) ($request->data()['page'] ?? 0) === 1);
})->with([
    'paused' => ImportStatus::Paused,
    'worker died' => ImportStatus::Running,
    'failed' => ImportStatus::Failed,
    'cancelled' => ImportStatus::Cancelled,
]);

it('queues one job per unfinished page on --resume', function (): void {
    neozenaCmdConfigure();
    Bus::fake([ImportCatalogPage::class]);
    $run = neozenaCmdStartedRun(ImportStatus::Failed, 3, [2]);

    $this->artisan('catalog:import-neozena', ['--resume' => (string) $run->id, '--no-wait' => true])->assertSuccessful();

    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->map(fn (ImportCatalogPage $job): int => $job->page)->all() === [1, 3]);
    expect($run->refresh()->status)->toBe(ImportStatus::Running)->and($run->pages_done)->toBe(1)->and($run->failure_message)->toBeNull();
});

it('refuses to resume runs that cannot continue', function (): void {
    neozenaCmdConfigure();

    $this->artisan('catalog:import-neozena', ['--resume' => '999999'])->expectsOutputToContain('does not exist')->assertFailed();

    $live = neozenaCmdStartedRun(ImportStatus::Running);
    $live->forceFill(['updated_at' => now()])->saveQuietly();
    $this->artisan('catalog:import-neozena', ['--resume' => (string) $live->id])
        ->expectsOutputToContain(__('catalog::imports.errors.still_running'))
        ->assertFailed();

    $live->update(['status' => ImportStatus::Completed]);
    $this->artisan('catalog:import-neozena', ['--resume' => (string) $live->id])
        ->expectsOutputToContain(__('catalog::imports.errors.already_completed'))
        ->assertFailed();
});

it('registers the daily queued --update-existing sync only when enabled in the Neozena settings', function (): void {
    expect(neozenaCmdScheduled())->toBe([]);

    neozenaCmdConfigure(['daily_sync' => false, 'daily_sync_at' => '04:15']);
    expect(neozenaCmdScheduled())->toBe([]);

    neozenaCmdConfigure(['base_url' => '', 'daily_sync' => true, 'daily_sync_at' => '04:15']);
    expect(neozenaCmdScheduled())->toBe([]);

    neozenaCmdConfigure(['daily_sync' => true, 'daily_sync_at' => '04:15']);
    $events = neozenaCmdScheduled();

    expect($events)->toHaveCount(1)
        ->and($events[0]->expression)->toBe('15 4 * * *')
        ->and($events[0]->command)->toContain('catalog:import-neozena')
        ->and($events[0]->command)->toContain('--update-existing')
        ->and($events[0]->command)->toContain('--force')
        ->and($events[0]->command)->toContain('--no-wait');
});

it('saves the Neozena section without an enabled switch or token', function (): void {
    $admin = integrationsAdmin('integrations.view', 'integrations.manage');
    $url = $this->centralUrl('/admin/integrations/neozena');

    $this->actingAs($admin, 'admin')
        ->putJson($url, ['base_url' => NEOZENA_CMD_BASE, 'daily_sync' => '1', 'daily_sync_at' => '25:99'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['daily_sync_at']);

    $this->putJson($url, ['base_url' => NEOZENA_CMD_BASE, 'daily_sync' => '1', 'daily_sync_at' => '02:45'])->assertOk();

    expect(app(SettingsRepositoryContract::class)->group('neozena'))->toMatchArray(['daily_sync' => true, 'daily_sync_at' => '02:45'])
        ->and(app(SettingsRepositoryContract::class)->group('neozena'))->not->toHaveKey('api_token');

    $this->get($url)->assertOk()
        ->assertSee(__('integrations::admin.sections.neozena.fields.daily_sync'))
        ->assertSee('type="time"', false)
        ->assertDontSee('name="enabled"', false);
});

it('imports published products, active variants and categories that stores see as new products', function (): void {
    neozenaCmdConfigure();
    neozenaCmdFake([[neozenaCmdProduct(1, ['status' => false])]]);
    [$tenant, $owner] = catalogStore('neozena-new');

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();

    $product = Product::query()->sole();
    expect($product->status)->toBe(ProductStatus::Published)
        ->and($product->published_at)->not->toBeNull()
        ->and($product->variants->every(fn (ProductVariant $variant): bool => $variant->status === RecordStatus::Active))->toBeTrue()
        ->and(Category::query()->where('external_source', 'neozena')->sole()->status)->toBe(RecordStatus::Active)
        ->and(Attribute::query()->where('external_source', 'neozena')->sole()->status)->toBe(RecordStatus::Active);

    $json = $this->actingAsVendor($owner, $tenant)
        ->getJson($this->tenantUrl($tenant, 'admin/catalog/products/new/data?draw=1&start=0&length=12'))
        ->assertOk()
        ->json();

    expect($json['recordsFiltered'])->toBe(1)->and(collect($json['data'])->pluck('card')->implode(' '))->toContain('Cli Shirt 1');

    // An admin's later change survives a refreshing re-run.
    $product->update(['status' => ProductStatus::Draft]);
    neozenaCmdFake([[neozenaCmdProduct(1, ['current_price' => '29.00'])]]);
    $this->artisan('catalog:import-neozena', ['--force' => true, '--update-existing' => true])->assertSuccessful();

    expect($product->refresh()->status)->toBe(ProductStatus::Draft)->and($product->base_price->minor)->toBe(2900);
});

it('logs the pluralised summary of an imported product', function (): void {
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    $sizes = ['XS', 'S', 'M', 'L', 'XL'];
    neozenaCmdFake([[neozenaCmdProduct(1, [
        'variants' => [['id' => 80, 'translations' => [['locale' => 'en', 'name' => 'Size']], 'options' => array_map(fn (int $i): array => ['id' => 850 + $i, 'value' => $sizes[$i]], range(0, 4))]],
        'variant_combinations' => array_map(fn (int $i): array => ['id' => 860 + $i, 'sku' => 'NZC-1-'.$sizes[$i], 'price' => '24.00', 'stock' => 1, 'option_ids' => [850 + $i]], range(0, 4)),
        'images' => array_map(fn (int $i): array => ['id' => 870 + $i, 'image_path' => 'https://cdn.neozena-cli.test/s'.$i.'.png'], range(0, 4)),
    ])]]);

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();

    expect(CatalogImportLog::query()->where('result', 'created')->value('message'))->toBe('5 variants · 5 images');
});

it('creates the default branch for imported stock when there is none, and reuses an existing default', function (): void {
    $country = Country::query()->where('iso2', strtoupper((string) config('app.default_country')))->first()
        ?? Country::factory()->iso(strtoupper((string) config('app.default_country')))->create();
    neozenaCmdConfigure();
    neozenaCmdMapCategory();
    neozenaCmdFake([[neozenaCmdProduct(1)]]);
    Branch::query()->withTrashed()->forceDelete();

    $this->artisan('catalog:import-neozena', ['--force' => true])->assertSuccessful();

    $branch = Branch::query()->sole();
    $product = Product::query()->sole();
    expect($branch->getTranslation('name', 'en'))->toBe('Main warehouse')
        ->and($branch->getTranslation('name', 'ar'))->toBe('المستودع الرئيسي')
        ->and($branch->is_active)->toBeTrue()
        ->and($branch->country_id)->toBe($country->id)
        ->and(DB::connection($branch->getConnectionName())->table('branch_default_countries')->where('country_id', $country->id)->value('branch_id'))->toBe($branch->id)
        ->and($product->stock)->toBe(10)
        ->and(CatalogImportLog::query()->where('message', 'like', '%'.__('catalog::imports.messages.no_branch').'%')->exists())->toBeFalse();

    expect(app(BranchService::class)->defaultOrCreate()->id)->toBe($branch->id)
        ->and(Branch::query()->count())->toBe(1);
});

it('marks the first active branch as default when none is, instead of creating one', function (): void {
    $branch = Branch::factory()->create();
    DB::connection($branch->getConnectionName())->table('branch_default_countries')->delete();

    expect(app(BranchService::class)->defaultOrCreate()->id)->toBe(Branch::query()->where('is_active', true)->orderBy('code')->first()->id)
        ->and(DB::connection($branch->getConnectionName())->table('branch_default_countries')->count())->toBeGreaterThan(0);
});
