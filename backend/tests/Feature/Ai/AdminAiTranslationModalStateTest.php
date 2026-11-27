<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Localization\Models\Language;
use App\Modules\Localization\Services\Translations\TranslationEditorService;

/*
| RQA-ADM-007: with nothing missing, the "Translate with AI" modal said "a Arabic
| translation" and kept its submit button enabled (the submit then failed with a 422
| nobody saw). The fragment now asks the modal to keep the submit disabled.
*/

it('disables the submit and shows a neutral message when no key is missing', function (string $locale, string $language): void {
    app()->setLocale($locale);

    $html = view('ai::admin.translation.static-keys-form', [
        'file' => app(TranslationEditorService::class)->findFile('ai::admin_translation'),
        'locale' => 'ar',
        'language' => $language,
        'estimates' => ['file' => ['keys' => 0, 'words' => 0, 'locked' => 0], 'all' => ['keys' => 0, 'words' => 0, 'locked' => 0]],
    ])->render();

    expect($html)->toMatch('/<form id="ai-keys-form"[^>]*\sdata-submit-disabled[\s>]/')
        ->and($html)->toContain(e(__('ai::admin_translation.keys.none', ['language' => $language])))
        ->and(__('ai::admin_translation.keys.none', ['language' => 'Arabic'], 'en'))->toBe('Every key already has a translation in Arabic.');
})->with([['en', 'Arabic'], ['ar', 'العربية']]);

it('keeps the submit enabled while keys are missing', function (): void {
    $this->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['dashboard.view', 'localization.languages.manage', 'localization.translations.manage']);
    $french = Language::query()->where('code', 'fr')->first() ?? Language::factory()->create(['code' => 'fr', 'name' => ['en' => 'French', 'ar' => 'الفرنسية'], 'native_name' => 'Français']);
    $french->update(['is_active' => true]);

    $html = $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/localization/ai-translation/static-keys/create?'.http_build_query(['file' => 'ai::admin_translation', 'locale' => 'fr'])))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-ai-keys-form')
        ->and($html)->not->toMatch('/<form id="ai-keys-form"[^>]*\sdata-submit-disabled[\s>]/');
});
