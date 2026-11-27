<?php

use App\Modules\Catalog\Models\ProductLabel;
use App\Shared\Support\Icon;

require_once __DIR__.'/../Catalog/CatalogHelpers.php';

/*
| <x-ui::icon-picker> (client request 2026-11-27): options carry the icon SVG and a
| readable, translated title; internal ad-* / tp-* icons are left out unless already set.
*/

it('builds icon picker options with titles and inline svg, without internal icons', function (): void {
    $options = collect(Icon::pickerOptions());

    expect($options)->not->toBeEmpty()
        ->and($options->pluck('value')->filter(fn (string $name): bool => str_starts_with($name, 'ad-') || str_starts_with($name, 'tp-'))->all())->toBe([])
        ->and($options->firstWhere('value', 'heart'))->toMatchArray(['label' => 'Heart'])
        ->and($options->firstWhere('value', 'heart')['svg'])->toStartWith('<svg width="18" height="18"')->toContain('currentColor')
        ->and($options->pluck('label')->all())->toBe($options->pluck('label')->sortBy(fn (string $label): string => $label, SORT_NATURAL | SORT_FLAG_CASE)->values()->all());

    app()->setLocale('ar');
    expect(collect(Icon::pickerOptions())->firstWhere('value', 'heart')['label'])->toBe('قلب')
        ->and(Icon::title('credit-card-2'))->toBe('بطاقة (بديل)');

    app()->setLocale('en');
    expect(collect(Icon::pickerOptions('ad-help'))->pluck('value'))->toContain('ad-help')
        ->and(Icon::title('ad-help'))->toBe('Ad help');
});

it('renders the label modal icon field as an icon picker', function (): void {
    $this->actingAs(catalogAdmin(), 'admin');

    $this->get($this->centralUrl('/admin/catalog/labels/create'))
        ->assertOk()
        ->assertSee('data-icon-picker', false)
        ->assertSee('data-icon-svg="&lt;svg width=&quot;18&quot;', false)
        ->assertSee('>Lightning</option>', false)
        ->assertDontSee('value="tp-home"', false);

    $label = ProductLabel::query()->create(['text' => ['en' => 'Fast'], 'icon' => 'zap', 'is_active' => true]);

    $this->get($this->centralUrl('/admin/catalog/labels/'.$label->id.'/edit'))
        ->assertOk()
        ->assertSee('value="zap" data-icon-svg=', false)
        ->assertSee('selected>Lightning</option>', false);
});
