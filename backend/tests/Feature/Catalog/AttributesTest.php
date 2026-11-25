<?php

use App\Modules\Catalog\Enums\AttributeDisplayType;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeOption;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;

require_once __DIR__.'/CatalogHelpers.php';

/*
| Attributes (ADM-015 / 015a): permissions, server-side list, create / edit with the
| translated options repeater, options in use, delete guard.
*/

function attributePayload(array $overrides = []): array
{
    return array_replace([
        'name' => ['en' => 'Color', 'ar' => 'اللون'],
        'display_type' => 'color',
        'status' => 'active',
        'options' => [
            ['name' => ['en' => 'Black', 'ar' => 'أسود'], 'swatch' => '#121212', 'is_active' => '1'],
            ['name' => ['en' => 'White', 'ar' => 'أبيض'], 'swatch' => '#ffffff', 'is_active' => '0'],
        ],
    ], $overrides);
}

it('redirects guests and forbids admins without attribute permissions', function (): void {
    $attribute = Attribute::factory()->create();

    $this->get($this->centralUrl('/admin/catalog/attributes'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(catalogAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/catalog/attributes'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/catalog/attributes/data'))->assertForbidden();

    $this->actingAs(catalogAdmin(['catalog.attributes.view']), 'admin');
    $this->get($this->centralUrl('/admin/catalog/attributes'))->assertOk();
    $this->getJson($this->centralUrl('/admin/catalog/attributes/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/catalog/attributes'), attributePayload())->assertForbidden();
    $this->putJson($this->centralUrl("/admin/catalog/attributes/{$attribute->id}"), attributePayload())->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/catalog/attributes/{$attribute->id}"))->assertForbidden();
});

it('serves the attributes table with options, usage and filters', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $size = Attribute::factory()->withOptions(['XS', 'S', 'M', 'L', 'XL', 'XXL'])->create(['name' => ['en' => 'Size', 'ar' => 'المقاس']]);
    Attribute::factory()->color()->create(['name' => ['en' => 'Color', 'ar' => 'اللون']]);
    Product::factory()->create()->attributes()->attach($size->id);

    $json = $this->getJson($this->centralUrl('/admin/catalog/attributes/data?draw=1&start=0&length=10'))->assertOk()->json();
    expect($json['recordsTotal'])->toBe(2);
    $row = collect($json['data'])->firstWhere('id', $size->id);
    expect($row['options'])->toContain('XS, S, M, L, XL')->toContain('+1')
        ->and($row['products_count'])->toContain('1');

    $filtered = $this->getJson($this->centralUrl('/admin/catalog/attributes/data?draw=1&start=0&length=10&display_type=color'))->json();
    expect($filtered['recordsFiltered'])->toBe(1);

    $searched = $this->getJson($this->centralUrl('/admin/catalog/attributes/data?draw=1&start=0&length=10&search[value]=المقاس'))->json();
    expect($searched['recordsFiltered'])->toBe(1);
});

it('renders the create and edit forms', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $attribute = Attribute::factory()->withOptions(['Small'])->create();

    $this->get($this->centralUrl('/admin/catalog/attributes/create'))->assertOk()->assertSee('options[0][name][en]', false);
    $this->get($this->centralUrl("/admin/catalog/attributes/{$attribute->id}/edit"))->assertOk()->assertSee('Small')->assertSee(__('catalog::attributes.usage.note'));
});

it('creates an attribute with ordered, translated options', function (): void {
    $this->actingAs(catalogAdmin(['catalog.attributes.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/catalog/attributes'), attributePayload())
        ->assertCreated()->assertJson(['redirect' => route('admin.catalog.attributes.index')]);

    $attribute = Attribute::query()->sole();
    expect($attribute->display_type)->toBe(AttributeDisplayType::Color)
        ->and($attribute->options->pluck('name')->all())->toBe(['Black', 'White'])
        ->and($attribute->options[0]->getTranslation('name', 'ar'))->toBe('أسود')
        ->and($attribute->options[1]->swatch)->toBe('#FFFFFF')
        ->and($attribute->options[1]->is_active)->toBeFalse();
});

it('validates the attribute and its options', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/catalog/attributes'), attributePayload([
        'name' => ['en' => ''],
        'display_type' => 'image',
        'options' => [['name' => ['en' => 'Red'], 'swatch' => 'red']],
    ]))->assertJsonValidationErrors(['name.en', 'name.ar', 'display_type', 'options.0.name.ar', 'options.0.swatch']);

    $this->postJson($this->centralUrl('/admin/catalog/attributes'), attributePayload(['options' => []]))->assertJsonValidationErrors('options');
});

it('updates, reorders, adds and removes options', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $attribute = Attribute::factory()->withOptions(['S', 'M', 'L'])->create();
    [$small, $medium, $large] = $attribute->options->all();

    $this->putJson($this->centralUrl("/admin/catalog/attributes/{$attribute->id}"), attributePayload([
        'display_type' => 'text',
        'options' => [
            ['id' => $large->id, 'name' => ['en' => 'Large', 'ar' => 'كبير'], 'is_active' => '1'],
            ['id' => $small->id, 'name' => ['en' => 'Small', 'ar' => 'صغير'], 'is_active' => '1'],
            ['name' => ['en' => 'XL', 'ar' => 'كبير جدا'], 'is_active' => '1'],
        ],
    ]))->assertOk();

    expect($attribute->refresh()->options->pluck('name')->all())->toBe(['Large', 'Small', 'XL'])
        ->and($attribute->options->pluck('swatch')->filter()->all())->toBe([])
        ->and(AttributeOption::withTrashed()->find($medium->id)->trashed())->toBeTrue();
});

it('keeps options used by variants and blocks deleting attributes used by products', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $attribute = Attribute::factory()->withOptions(['S', 'M'])->create();
    [$small, $medium] = $attribute->options->all();
    $variant = ProductVariant::factory()->create();
    $variant->options()->attach($medium->id);
    $variant->product->attributes()->attach($attribute->id);

    $this->putJson($this->centralUrl("/admin/catalog/attributes/{$attribute->id}"), attributePayload([
        'display_type' => 'text',
        'options' => [['id' => $small->id, 'name' => ['en' => 'S', 'ar' => 'S'], 'is_active' => '1']],
    ]))->assertUnprocessable()->assertJson(['success' => false]);
    expect($medium->refresh()->trashed())->toBeFalse();

    $this->get($this->centralUrl("/admin/catalog/attributes/{$attribute->id}/edit"))->assertSee(__('catalog::attributes.option_locked'), false);

    $this->deleteJson($this->centralUrl("/admin/catalog/attributes/{$attribute->id}"))->assertUnprocessable();

    $free = Attribute::factory()->withOptions(['A'])->create();
    $this->deleteJson($this->centralUrl("/admin/catalog/attributes/{$free->id}"))->assertOk();
    expect($free->refresh()->trashed())->toBeTrue()
        ->and(AttributeOption::query()->where('attribute_id', $free->id)->count())->toBe(0);
});
