<?php

use App\Modules\Storefront\Enums\CustomThemeTemplate;
use App\Modules\Storefront\Services\CustomThemes\StarterThemeKit;
use App\Modules\Storefront\Support\CustomThemes\ThemeFixtures;
use App\Modules\Storefront\Support\CustomThemes\ThemeManifest;
use App\Modules\Storefront\Support\CustomThemes\ThemePackageReport;
use App\Modules\Storefront\Support\CustomThemes\ThemePackageValidator;
use App\Modules\Storefront\Support\CustomThemes\ThemeVariableReference;
use App\Modules\Storefront\Support\CustomThemes\Twig\ThemeTwigFactory;
use Twig\Markup;

require_once __DIR__.'/CustomThemeHelpers.php';

/*
| SF-ALL-006: every page template of the starter theme renders with the fixtures (the
| same presenters as the live storefront) in English and Arabic (RTL); the documented
| variable reference matches the presenters (docs/themes/README.md is up to date).
*/

it('renders every page of the starter theme in en and ar', function (string $locale): void {
    $contents = app(StarterThemeKit::class)->contents();
    $manifest = ThemeManifest::validate(json_decode($contents['theme.json'], true))['manifest'];
    $runtime = ThemePackageValidator::fixtureRuntime($contents, $manifest, $locale);
    $twig = ThemeTwigFactory::make(ThemePackageReport::templatesOf($contents), $runtime);

    foreach (CustomThemeTemplate::pages() as $template) {
        $context = ThemeFixtures::context($template, settings: $runtime->settings);
        $context['store']['locale'] = $locale;
        $context['store']['dir'] = $locale === 'ar' ? 'rtl' : 'ltr';
        $html = ThemeTwigFactory::render($twig, $template->value.'.twig', $context);

        expect($html)->toContain('data-nt-head')->toContain('dir="'.$context['store']['dir'].'"');
    }

    expect($runtime->warnings)->toBe([]);
})->with(['en', 'ar']);

it('hands templates plain data only', function (): void {
    foreach (CustomThemeTemplate::pages() as $template) {
        $context = ThemeFixtures::context($template);
        array_walk_recursive($context, function (mixed $value): void {
            expect($value === null || is_scalar($value) || $value instanceof Markup)->toBeTrue();
        });
    }

    expect(ThemeFixtures::context(CustomThemeTemplate::Product)['product']['name'])->toBe('Cotton tee')
        ->and(ThemeFixtures::context(CustomThemeTemplate::Cart)['cart_page']['totals']['grand_total'])->toBe('$44.98');
});

it('keeps docs/themes/README.md in step with the presenters', function (): void {
    $this->artisan('storefront:theme-docs', ['--check' => true])->assertSuccessful();

    expect(ThemeVariableReference::markdown())->toContain('`product.price`')->toContain('`store.dir`');
});
