<?php

use App\Modules\Catalog\Models\ProductLabel;
use App\Modules\Localization\Models\Country;

require_once __DIR__.'/CatalogHelpers.php';

/*
| Product labels (ADM-016 / 016a): permissions, list + preview, modal form, placeholder
| validation, active switch, drag-and-drop order and delete.
*/

function labelPayload(array $overrides = []): array
{
    return array_replace([
        'text' => ['en' => 'Only {stock} left', 'ar' => 'متبقي {stock} فقط'],
        'icon' => 'alert-triangle',
        'sort_order' => '',
        'country_ids' => [],
        'is_active' => '1',
    ], $overrides);
}

it('redirects guests and forbids admins without label permissions', function (): void {
    $label = ProductLabel::factory()->create();

    $this->get($this->centralUrl('/admin/catalog/labels'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(catalogAdmin(['catalog.labels.view']), 'admin');
    $this->get($this->centralUrl('/admin/catalog/labels'))->assertOk();
    $this->getJson($this->centralUrl('/admin/catalog/labels/create'))->assertForbidden();
    $this->postJson($this->centralUrl('/admin/catalog/labels'), labelPayload())->assertForbidden();
    $this->putJson($this->centralUrl("/admin/catalog/labels/{$label->id}"), labelPayload())->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/catalog/labels/{$label->id}/active"), ['is_active' => 0])->assertForbidden();
    $this->putJson($this->centralUrl('/admin/catalog/labels/order'), ['ids' => [$label->id]])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/catalog/labels/{$label->id}"))->assertForbidden();
});

it('lists labels with the shopper preview and shows the empty state', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/catalog/labels'))->assertOk()->assertSee(__('catalog::labels.empty_title'));

    ProductLabel::factory()->create(['text' => ['en' => 'Only {stock} left', 'ar' => 'متبقي {stock}']]);
    ProductLabel::factory()->inactive()->create(['text' => ['en' => 'Made in Saudi Arabia', 'ar' => 'صنع في السعودية']]);

    $this->get($this->centralUrl('/admin/catalog/labels'))
        ->assertOk()
        ->assertSee('Only {stock} left')
        ->assertSee('Only 4 left')
        ->assertSee('Made in Saudi Arabia')
        ->assertSee(__('catalog::labels.kind_static'));
});

it('renders the modal form fragments', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $label = ProductLabel::factory()->create();

    $this->get($this->centralUrl('/admin/catalog/labels/create'))->assertOk()->assertSee('label-form', false)->assertSee('{delivery_date}');
    $this->get($this->centralUrl("/admin/catalog/labels/{$label->id}/edit"))->assertOk()->assertSee('Delivered by {delivery_date}');
});

it('frames the label form in the full panel page when opened by URL (QA-P2-008)', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $label = ProductLabel::factory()->create();

    $this->get($this->centralUrl('/admin/catalog/labels/create'))
        ->assertOk()
        ->assertSee('ui-shell-main', false)
        ->assertSee('data-page="admin-catalog"', false)
        ->assertSee('data-success-url="'.route('admin.catalog.labels.index').'"', false)
        ->assertSee('form="label-form"', false);

    $this->get($this->centralUrl("/admin/catalog/labels/{$label->id}/edit"))
        ->assertOk()->assertSee('ui-shell-main', false)->assertSee(__('catalog::labels.edit_title'));

    $this->get($this->centralUrl('/admin/catalog/labels/create'), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('label-form', false)->assertDontSee('ui-shell-main', false)->assertDontSee('<html', false);
});

it('creates and updates a label with countries and a computed position', function (): void {
    $this->actingAs(catalogAdmin(['catalog.labels.manage']), 'admin');
    ProductLabel::factory()->create(['sort_order' => 4]);
    $country = Country::factory()->create();

    $this->postJson($this->centralUrl('/admin/catalog/labels'), labelPayload(['country_ids' => [$country->id]]))->assertCreated();

    $label = ProductLabel::query()->latest('id')->first();
    expect($label->getTranslation('text', 'ar'))->toBe('متبقي {stock} فقط')
        ->and($label->sort_order)->toBe(5)
        ->and($label->country_ids)->toBe([$country->id])
        ->and($label->icon)->toBe('alert-triangle');

    $this->putJson($this->centralUrl("/admin/catalog/labels/{$label->id}"), labelPayload(['text' => ['en' => 'Free shipping over {free_shipping_min}', 'ar' => 'شحن مجاني فوق {free_shipping_min}'], 'is_active' => '0', 'sort_order' => '1']))
        ->assertOk();

    expect($label->refresh()->is_active)->toBeFalse()
        ->and($label->sort_order)->toBe(1)
        ->and($label->country_ids)->toBeNull();
});

it('rejects unknown placeholders, icons and missing translations', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/catalog/labels'), labelPayload([
        'text' => ['en' => 'Ships {tomorrow}', 'ar' => ''],
        'icon' => 'not-an-icon',
        'country_ids' => [999999],
    ]))->assertJsonValidationErrors(['text.en', 'text.ar', 'icon', 'country_ids.0']);
});

it('toggles, sorts and deletes labels', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');
    $first = ProductLabel::factory()->create(['sort_order' => 0]);
    $second = ProductLabel::factory()->create(['sort_order' => 1]);

    $this->patchJson($this->centralUrl("/admin/catalog/labels/{$first->id}/active"), ['is_active' => 0])->assertOk();
    expect($first->refresh()->is_active)->toBeFalse();

    $this->putJson($this->centralUrl('/admin/catalog/labels/order'), ['ids' => [$second->id, $first->id]])->assertOk();
    expect($second->refresh()->sort_order)->toBe(0)->and($first->refresh()->sort_order)->toBe(1);

    $this->putJson($this->centralUrl('/admin/catalog/labels/order'), ['ids' => [$second->id]])->assertUnprocessable();

    $this->deleteJson($this->centralUrl("/admin/catalog/labels/{$first->id}"))->assertOk();
    expect($first->refresh()->trashed())->toBeTrue();
});
