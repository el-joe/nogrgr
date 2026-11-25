<?php

use App\Modules\Integrations\Repositories\Contracts\SettingsRepositoryContract;
use App\Modules\Localization\Models\Language;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/IntegrationsHelpers.php';

/*
| Admin › Settings › General (ADM-101): access, validation, translatable name,
| branding images on the public disk, default language via Localization.
*/

function generalSettingsLanguages(): void
{
    Language::factory()->create(['code' => 'en', 'is_active' => true, 'is_default' => true]);
    Language::factory()->create(['code' => 'ar', 'is_active' => true, 'is_default' => false]);
}

function generalSettingsPayload(array $overrides = []): array
{
    return array_replace([
        'platform_name' => ['en' => 'Nogrgr', 'ar' => 'نقرقر'],
        'support_email' => 'Support@Nogrgr.test',
        'support_phone' => '0501234567',
        'support_phone_country' => 'sa',
        'timezone' => 'Asia/Riyadh',
        'default_language' => 'ar',
        'archive_days' => '365',
        'socials' => ['instagram' => 'https://instagram.com/nogrgr', 'x' => '', 'tiktok' => '', 'linkedin' => '', 'facebook' => '', 'youtube' => ''],
    ], $overrides);
}

it('redirects guests and forbids admins without the settings permission', function (): void {
    $this->get($this->centralUrl('/admin/settings/general'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(integrationsAdmin(), 'admin')->get($this->centralUrl('/admin/settings/general'))->assertForbidden();
    $this->actingAs(integrationsAdmin('settings.view'), 'admin')->putJson($this->centralUrl('/admin/settings/general'), [])->assertForbidden();
});

it('renders the general settings page', function (): void {
    generalSettingsLanguages();

    $this->actingAs(integrationsAdmin('settings.manage'), 'admin')
        ->get($this->centralUrl('/admin/settings/general'))
        ->assertOk()
        ->assertSee(__('integrations::general.branding'))
        ->assertSee(route('admin.settings.general.update'), false);
});

it('saves the settings, the logo and the default language', function (): void {
    Storage::fake('public');
    generalSettingsLanguages();

    $this->actingAs(integrationsAdmin('settings.manage'), 'admin')
        ->put($this->centralUrl('/admin/settings/general'), generalSettingsPayload([
            'logo_light' => [UploadedFile::fake()->image('logo.png', 400, 120)],
            'favicon' => [UploadedFile::fake()->image('favicon.png', 512, 512)],
        ]), ['Accept' => 'application/json'])
        ->assertOk();

    $settings = app(SettingsRepositoryContract::class)->group('general');

    expect($settings['platform_name'])->toBe(['en' => 'Nogrgr', 'ar' => 'نقرقر'])
        ->and($settings['support_email'])->toBe('support@nogrgr.test')
        ->and($settings['support_phone'])->toBe('+966501234567')
        ->and($settings['archive_days'])->toBe(365)
        ->and($settings['socials']['instagram'])->toBe('https://instagram.com/nogrgr')
        ->and($settings['logo_dark'] ?? null)->toBeNull()
        ->and(Language::query()->where('code', 'ar')->value('is_default'))->toBeTrue()
        ->and(config('app.name'))->toBe('Nogrgr');

    Storage::disk('public')->assertExists($settings['logo_light']);
    Storage::disk('public')->assertExists($settings['favicon']);

    // Keep the logo, drop the favicon.
    $this->actingAs(integrationsAdmin('settings.manage'), 'admin')
        ->put($this->centralUrl('/admin/settings/general'), generalSettingsPayload(['logo_light_keep' => ['current']]), ['Accept' => 'application/json'])
        ->assertOk();

    $after = app(SettingsRepositoryContract::class)->group('general');
    expect($after['logo_light'])->toBe($settings['logo_light'])->and($after['favicon'])->toBeNull();
    Storage::disk('public')->assertMissing($settings['favicon']);
});

it('validates general settings', function (): void {
    Storage::fake('public');
    generalSettingsLanguages();

    $this->actingAs(integrationsAdmin('settings.manage'), 'admin')
        ->put($this->centralUrl('/admin/settings/general'), generalSettingsPayload([
            'platform_name' => ['en' => '', 'ar' => ''],
            'support_email' => 'nope',
            'support_phone' => '123',
            'timezone' => 'Mars/Base',
            'default_language' => 'xx',
            'archive_days' => '0',
            'socials' => ['instagram' => 'javascript:alert(1)'],
            'favicon' => [UploadedFile::fake()->create('favicon.svg', 4, 'image/svg+xml')],
        ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platform_name.en', 'platform_name.ar', 'support_email', 'support_phone', 'timezone', 'default_language', 'archive_days', 'socials.instagram', 'favicon.0']);
});
