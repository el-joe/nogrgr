<?php

use App\Modules\Catalog\Enums\RecordStatus;
use App\Modules\Catalog\Jobs\ConvertCategoryThumbnail;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/CatalogHelpers.php';

/*
| Categories (ADM-014 / 014a): permissions, tree page, create / edit with translations,
| thumbnail and parent, cycle-free re-parenting, featured switch and delete.
*/

function categoryPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => ['en' => 'Audio', 'ar' => 'الصوتيات'],
        'description' => ['en' => 'Headphones and speakers', 'ar' => 'سماعات ومكبرات'],
        'meta_title' => ['en' => 'Audio deals'],
        'slug' => '',
        'parent_id' => '',
        'sort_order' => '',
        'status' => 'active',
        'is_featured' => '1',
        'show_in_menu' => '0',
    ], $overrides);
}

it('redirects guests and forbids admins without category permissions', function (): void {
    $category = Category::factory()->create();

    $this->get($this->centralUrl('/admin/catalog/categories'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(catalogAdmin([]), 'admin');

    $this->getJson($this->centralUrl('/admin/catalog/categories'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/catalog/categories/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/catalog/categories'), categoryPayload())->assertForbidden();
    $this->putJson($this->centralUrl("/admin/catalog/categories/{$category->id}"), categoryPayload())->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/catalog/categories/{$category->id}/featured"), ['is_featured' => 1])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/catalog/categories/{$category->id}"))->assertForbidden();
    $this->putJson($this->centralUrl('/admin/catalog/categories/tree'), ['tree' => [['id' => $category->id]]])->assertForbidden();
});

it('lets catalog.categories.view see the tree but not change it', function (): void {
    $parent = Category::factory()->create(['name' => ['en' => 'Electronics', 'ar' => 'إلكترونيات']]);
    Category::factory()->childOf($parent)->create(['name' => ['en' => 'Earbuds', 'ar' => 'سماعات']]);
    $this->actingAs(catalogAdmin(['catalog.categories.view']), 'admin');

    $this->get($this->centralUrl('/admin/catalog/categories'))
        ->assertOk()
        ->assertSee('Electronics')
        ->assertSee('Earbuds')
        ->assertDontSee(route('admin.catalog.categories.create'), false);

    $this->getJson($this->centralUrl('/admin/catalog/categories/create'))->assertForbidden();
});

it('shows the empty state when there are no categories', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/catalog/categories'))->assertOk()->assertSee(__('catalog::categories.empty_title'));
});

it('renders the create and edit forms with every locale tab and parent choices', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $parent = Category::factory()->create(['name' => ['en' => 'Electronics', 'ar' => 'إلكترونيات']]);
    $child = Category::factory()->childOf($parent)->create(['name' => ['en' => 'Audio', 'ar' => 'الصوتيات']]);

    $this->get($this->centralUrl('/admin/catalog/categories/create'))->assertOk()->assertSee('name[ar]', false)->assertSee('Electronics');

    $this->get($this->centralUrl("/admin/catalog/categories/{$parent->id}/edit"))
        ->assertOk()
        ->assertSee('إلكترونيات')
        ->assertDontSee('<option value="'.$child->id.'"', false);
});

it('creates a category with translations, a generated slug and a converted thumbnail', function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->actingAs(catalogAdmin(['catalog.categories.manage']), 'admin');

    $this->post($this->centralUrl('/admin/catalog/categories'), categoryPayload([
        'thumbnail' => [UploadedFile::fake()->image('audio.png', 400, 400)],
    ]), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJson(['success' => true, 'redirect' => route('admin.catalog.categories.index')]);

    $category = Category::query()->sole();
    expect($category->getTranslation('name', 'ar'))->toBe('الصوتيات')
        ->and($category->slug)->toBe('audio')
        ->and($category->is_featured)->toBeTrue()
        ->and($category->show_in_menu)->toBeFalse()
        ->and($category->status)->toBe(RecordStatus::Active)
        ->and($category->getTranslation('meta_title', 'ar', false))->toBe('')
        ->and($category->thumbnail_path)->not->toBeNull();

    Storage::disk('public')->assertExists($category->thumbnail_path);
    Queue::assertPushed(ConvertCategoryThumbnail::class, fn (ConvertCategoryThumbnail $job): bool => $job->categoryId === $category->id);
});

it('writes webp and avif derivatives of the thumbnail', function (): void {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('audio.png', 400, 400)->store('catalog/categories', 'public');
    $category = Category::factory()->create(['thumbnail_path' => $path]);

    dispatch_sync(new ConvertCategoryThumbnail($category->id));

    $category->refresh();
    expect($category->thumbnail_webp_path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($category->thumbnail_webp_path);

    if (function_exists('imageavif')) {
        Storage::disk('public')->assertExists((string) $category->thumbnail_avif_path);
    }
});

it('makes slugs unique and validates required translations and the thumbnail', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    Category::factory()->create(['slug' => 'audio']);

    $this->postJson($this->centralUrl('/admin/catalog/categories'), categoryPayload())->assertCreated();
    expect(Category::query()->latest('id')->first()->slug)->toBe('audio-2');

    $this->post($this->centralUrl('/admin/catalog/categories'), categoryPayload([
        'name' => ['en' => '', 'ar' => ''],
        'slug' => 'Not A Slug',
        'status' => 'archived',
        'thumbnail' => [UploadedFile::fake()->image('tiny.png', 50, 50)],
    ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name.en', 'name.ar', 'slug', 'status', 'thumbnail.0']);
});

it('updates a category, moves it under a parent and removes the thumbnail', function (): void {
    Storage::fake('public');
    $this->actingAs(catalogAdmin(), 'admin');
    $parent = Category::factory()->create();
    Storage::disk('public')->put('catalog/categories/old.png', 'x');
    $category = Category::factory()->create(['thumbnail_path' => 'catalog/categories/old.png']);

    $this->putJson($this->centralUrl("/admin/catalog/categories/{$category->id}"), categoryPayload([
        'name' => ['en' => 'Sound', 'ar' => 'الصوت'],
        'slug' => 'sound',
        'parent_id' => $parent->id,
        'status' => 'inactive',
    ]))->assertOk()->assertJson(['success' => true]);

    $category->refresh();
    expect($category->parent_id)->toBe($parent->id)
        ->and($category->slug)->toBe('sound')
        ->and($category->status)->toBe(RecordStatus::Inactive)
        ->and($category->thumbnail_path)->toBeNull();
    Storage::disk('public')->assertMissing('catalog/categories/old.png');
});

it('rejects re-parenting a category under itself or a descendant', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();
    $grandchild = Category::factory()->childOf($child)->create();

    $this->putJson($this->centralUrl("/admin/catalog/categories/{$root->id}"), categoryPayload(['parent_id' => $root->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    $this->putJson($this->centralUrl("/admin/catalog/categories/{$root->id}"), categoryPayload(['parent_id' => $grandchild->id]))
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'message' => __('catalog::categories.parent_cycle')]);

    expect($root->refresh()->parent_id)->toBeNull();
});

it('toggles featured from the tree', function (): void {
    $this->actingAs(catalogAdmin(['catalog.categories.manage']), 'admin');
    $category = Category::factory()->create();

    $this->patchJson($this->centralUrl("/admin/catalog/categories/{$category->id}/featured"), ['is_featured' => 1])->assertOk();
    expect($category->refresh()->is_featured)->toBeTrue();

    $this->patchJson($this->centralUrl("/admin/catalog/categories/{$category->id}/featured"), ['is_featured' => 0])->assertOk();
    expect($category->refresh()->is_featured)->toBeFalse();

    $this->patchJson($this->centralUrl("/admin/catalog/categories/{$category->id}/featured"), [])->assertJsonValidationErrors('is_featured');
});

it('deletes a leaf category but not one with sub-categories', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();
    Product::factory()->inCategory($child)->create();

    $this->deleteJson($this->centralUrl("/admin/catalog/categories/{$parent->id}"))->assertUnprocessable();
    expect($parent->refresh()->trashed())->toBeFalse();

    $this->deleteJson($this->centralUrl("/admin/catalog/categories/{$child->id}"))->assertOk();
    expect($child->refresh()->trashed())->toBeTrue();
});
