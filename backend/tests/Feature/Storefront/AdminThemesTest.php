<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Storefront\Database\Seeders\ThemeCatalogSeeder;
use App\Modules\Storefront\Enums\ThemeFamily;
use App\Modules\Storefront\Enums\ThemePartType;
use App\Modules\Storefront\Models\Theme;
use App\Modules\Storefront\Models\ThemePart;
use App\Modules\Storefront\Support\ThemeSectionLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once dirname(__DIR__).'/Commerce/CommerceHelpers.php';

/*
| Admin theme catalogue (decisions #119 / #120): ADM-086 / 086a themes (13 seeded, activate /
| deactivate, platform default must be active, edit + preview image, allowed countries),
| ADM-087 home sections (order + on / off, reset) and ADM-088 / 088a template parts
| (declared fields, one default per theme + type, switch, delete) with their permissions.
*/

beforeEach(function (): void {
    $this->seed(ThemeCatalogSeeder::class);
    $this->theme = fn (string $slug): Theme => Theme::query()->where('slug', $slug)->firstOrFail();
    $this->url = fn (string $path = ''): string => $this->centralUrl('/admin/storefront/'.$path);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest'];
});

function themePartPayload(int $themeId, array $overrides = []): array
{
    return array_merge([
        'theme_id' => $themeId,
        'type' => 'announcement',
        'name' => 'Ramadan bar',
        'settings' => ['message' => ['en' => 'Free delivery this week', 'ar' => 'توصيل مجاني هذا الأسبوع'], 'link' => '/offers', 'tone' => 'brand'],
        'is_active' => '1',
        'is_default' => '1',
    ], $overrides);
}

it('seeds the 13 themes with Ecommet as the platform default, idempotently', function (): void {
    $this->seed(ThemeCatalogSeeder::class);

    expect(Theme::query()->count())->toBe(13)
        ->and(Theme::query()->where('is_default', true)->pluck('slug')->all())->toBe(['ecommet'])
        ->and(Theme::query()->where('family', 'elora')->count())->toBe(6)
        ->and(Theme::query()->where('family', 'souqify')->count())->toBe(6);
});

it('guards the theme pages and actions with the storefront theme permissions', function (): void {
    $theme = ($this->theme)('elora-2');

    $this->get(($this->url)('themes'))->assertRedirect($this->centralUrl('/admin/login'));
    $this->get(($this->url)('template-parts'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(commerceAdmin([]), 'admin');
    $this->getJson(($this->url)('themes'))->assertForbidden();
    $this->getJson(($this->url)('home-sections'))->assertForbidden();
    $this->getJson(($this->url)('template-parts/data'))->assertForbidden();

    $this->actingAs(commerceAdmin(['storefront.themes.view', 'storefront.template-parts.view']), 'admin');
    $this->get(($this->url)('themes'))->assertOk()->assertSee('Elora Bloom')->assertDontSee(route('admin.storefront.themes.edit', $theme), false);
    $this->get(($this->url)('template-parts'))->assertOk();
    $this->postJson(($this->url)("themes/{$theme->id}/deactivate"))->assertForbidden();
    $this->postJson(($this->url)("themes/{$theme->id}/default"))->assertForbidden();
    $this->putJson(($this->url)("home-sections/{$theme->id}"), ['order' => ['hero']])->assertForbidden();
    $this->postJson(($this->url)('template-parts'), themePartPayload($theme->id))->assertForbidden();
});

it('lists every theme with status, default badge and the confirm actions', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    ($this->theme)('souqify-4')->update(['is_active' => false]);

    $response = $this->get(($this->url)('themes'))->assertOk();
    $body = $response->getContent();

    expect(substr_count((string) $body, 'data-theme-card='))->toBe(13);
    $response->assertSee(__('content::admin_themes.default'))
        ->assertSee(route('admin.storefront.themes.activate', ($this->theme)('souqify-4')), false)
        ->assertSee(route('admin.storefront.themes.default', ($this->theme)('elora-1')), false)
        ->assertDontSee(route('admin.storefront.themes.deactivate', ($this->theme)('ecommet')), false)
        ->assertSee(__('content::admin_themes.confirm.deactivate'))
        ->assertSee(__('content::admin_themes.custom.empty'));
});

it('activates and deactivates themes but never the platform default', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    $elora = ($this->theme)('elora-3');

    $this->postJson(($this->url)("themes/{$elora->id}/deactivate"))->assertOk()->assertJsonPath('redirect', route('admin.storefront.themes.index'));
    expect($elora->fresh()->is_active)->toBeFalse();

    $this->postJson(($this->url)("themes/{$elora->id}/activate"))->assertOk();
    expect($elora->fresh()->is_active)->toBeTrue();

    $this->postJson(($this->url)('themes/'.($this->theme)('ecommet')->id.'/deactivate'))->assertUnprocessable()->assertJsonValidationErrors('is_active');
    expect(($this->theme)('ecommet')->is_active)->toBeTrue();
});

it('sets one platform default, which must be active', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    $souqify = ($this->theme)('souqify-1');
    $off = ($this->theme)('elora-6');
    $off->update(['is_active' => false]);

    $this->postJson(($this->url)("themes/{$off->id}/default"))->assertUnprocessable()->assertJsonValidationErrors('is_default');

    $this->postJson(($this->url)("themes/{$souqify->id}/default"))->assertOk();
    expect(Theme::query()->where('is_default', true)->pluck('slug')->all())->toBe(['souqify-1']);
});

it('edits the name, description and preview image of a theme', function (): void {
    Storage::fake('public');
    $this->actingAs(commerceAdmin(), 'admin');
    $theme = ($this->theme)('elora-1');

    $this->get(($this->url)("themes/{$theme->id}/edit"), $this->ajax)->assertOk()->assertSee('theme-form', false);

    $this->post(($this->url)("themes/{$theme->id}"), [
        '_method' => 'PUT',
        'name' => ['en' => 'Elora Classic', 'ar' => 'إيلورا كلاسيك'],
        'description' => ['en' => 'Editorial fashion layout.', 'ar' => ''],
        'preview' => [UploadedFile::fake()->image('preview.png', 800, 600)],
    ], ['Accept' => 'application/json'])->assertOk();

    $theme->refresh();
    expect($theme->getTranslation('name', 'en'))->toBe('Elora Classic')
        ->and($theme->getTranslation('description', 'ar', false))->toBe('')
        ->and($theme->preview_path)->not->toBeNull();
    Storage::disk('public')->assertExists($theme->preview_path);
    $old = $theme->preview_path;

    // No new upload and no kept tile = remove the uploaded preview.
    $this->putJson(($this->url)("themes/{$theme->id}"), ['name' => ['en' => 'Elora Classic', 'ar' => 'إيلورا كلاسيك']])->assertOk();
    expect($theme->fresh()->preview_path)->toBeNull();
    Storage::disk('public')->assertMissing($old);

    $this->putJson(($this->url)("themes/{$theme->id}"), ['name' => ['en' => '']])->assertUnprocessable()->assertJsonValidationErrors('name.en');
});

it('saves the allowed countries of a theme (none = all countries)', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    $sa = Country::factory()->iso('SA')->create();
    $ae = Country::factory()->iso('AE')->create();
    $theme = ($this->theme)('souqify-2');

    $this->get(($this->url)("themes/{$theme->id}/countries"), $this->ajax)->assertOk()->assertSee('data-country-all', false);

    $this->putJson(($this->url)("themes/{$theme->id}/countries"), ['country_ids' => [$sa->id, $ae->id]])->assertOk();
    expect($theme->countries()->pluck('countries.id')->sort()->values()->all())->toBe(collect([$sa->id, $ae->id])->sort()->values()->all())
        ->and($theme->fresh()->allowsCountry($sa->id))->toBeTrue();

    $this->putJson(($this->url)("themes/{$theme->id}/countries"), [])->assertOk();
    expect($theme->countries()->count())->toBe(0)
        ->and($theme->fresh()->allowsCountry($ae->id))->toBeTrue();

    $this->putJson(($this->url)("themes/{$theme->id}/countries"), ['country_ids' => [999999]])->assertUnprocessable();
});

it('orders and switches the home sections of a theme and resets them', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    $theme = ($this->theme)('elora-2');
    $keys = ThemeSectionLibrary::keys(ThemeFamily::Elora, 2);

    $this->get(($this->url)('home-sections?theme='.$theme->id))->assertOk()
        ->assertSee(__('content::admin_themes.sections.types.hero'))
        ->assertSee('data-section-item', false);

    $order = array_reverse($keys);
    $enabled = array_values(array_diff($keys, ['badges']));
    $this->putJson(($this->url)("home-sections/{$theme->id}"), ['order' => $order, 'enabled' => $enabled])->assertOk();

    $saved = $theme->fresh()->home_sections;
    expect(array_column($saved, 'key'))->toBe($order)
        ->and(collect($saved)->firstWhere('key', 'badges')['enabled'])->toBeFalse();

    $this->putJson(($this->url)("home-sections/{$theme->id}"), ['order' => $keys, 'enabled' => []])->assertUnprocessable()->assertJsonValidationErrors('enabled');

    $this->deleteJson(($this->url)("home-sections/{$theme->id}"))->assertOk();
    expect($theme->fresh()->home_sections)->toBeNull();
});

it('creates template parts with the fields of their type and keeps one default per theme and type', function (): void {
    Storage::fake('public');
    $this->actingAs(commerceAdmin(), 'admin');
    $theme = ($this->theme)('ecommet');

    $this->get(($this->url)('template-parts/create?theme='.$theme->id), $this->ajax)->assertOk()->assertSee('data-part-fields="product_card"', false);

    $this->postJson(($this->url)('template-parts'), themePartPayload($theme->id, ['settings' => ['tone' => 'dark']]))
        ->assertUnprocessable()->assertJsonValidationErrors('settings.message.en');
    $this->postJson(($this->url)('template-parts'), themePartPayload($theme->id, ['settings' => ['message' => ['en' => 'Hi'], 'tone' => 'neon']]))
        ->assertUnprocessable()->assertJsonValidationErrors('settings.tone');

    $this->postJson(($this->url)('template-parts'), themePartPayload($theme->id))->assertCreated();
    $first = ThemePart::query()->sole();
    expect($first->type)->toBe(ThemePartType::Announcement)
        ->and($first->is_default)->toBeTrue()
        ->and($first->settings['message'])->toEqual(['en' => 'Free delivery this week', 'ar' => 'توصيل مجاني هذا الأسبوع']);

    $this->postJson(($this->url)('template-parts'), themePartPayload($theme->id, ['name' => 'Eid bar']))->assertCreated();
    expect(ThemePart::query()->where('is_default', true)->pluck('name')->all())->toBe(['Eid bar']);

    $this->postJson(($this->url)("template-parts/{$first->id}/default"))->assertOk();
    expect(ThemePart::query()->where('is_default', true)->pluck('name')->all())->toBe(['Ramadan bar']);

    $this->postJson(($this->url)('template-parts'), themePartPayload($theme->id, ['type' => 'product_card', 'name' => 'Clean cards', 'settings' => ['show_rating' => '0', 'show_hover_image' => '1']]))->assertCreated();
    $card = ThemePart::query()->where('type', 'product_card')->sole();
    expect($card->settings)->toBe(['show_rating' => false, 'show_hover_image' => true])
        ->and(ThemePart::query()->where('is_default', true)->count())->toBe(2);

    $this->getJson(($this->url)('template-parts/data?type=product_card&draw=1'), $this->ajax)->assertOk()->assertJsonPath('recordsFiltered', 1);
});

it('edits, switches and deletes a template part', function (): void {
    $this->actingAs(commerceAdmin(), 'admin');
    $theme = ($this->theme)('souqify-3');
    $this->postJson(($this->url)('template-parts'), themePartPayload($theme->id, ['type' => 'footer', 'name' => 'Footer', 'settings' => ['copyright' => ['en' => '© :year Souq', 'ar' => '© :year سوق']]]))->assertCreated();
    $part = ThemePart::query()->sole();

    $this->get(($this->url)("template-parts/{$part->id}/edit"), $this->ajax)->assertOk()->assertSee('© :year Souq', false);
    $this->putJson(($this->url)("template-parts/{$part->id}"), themePartPayload($theme->id, ['type' => 'header', 'name' => 'Sticky header', 'settings' => ['sticky' => '1'], 'is_default' => '0']))->assertOk();
    expect($part->fresh()->type)->toBe(ThemePartType::Header)->and($part->fresh()->settings)->toBe(['sticky' => true])->and($part->fresh()->is_default)->toBeFalse();

    $this->patchJson(($this->url)("template-parts/{$part->id}/active"), ['is_active' => 0])->assertOk();
    expect($part->fresh()->is_active)->toBeFalse();

    $this->deleteJson(($this->url)("template-parts/{$part->id}"))->assertOk();
    expect(ThemePart::query()->count())->toBe(0);
});
