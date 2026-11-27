<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Models\CustomTheme;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Notifications\CustomThemeRenderFailedNotification;
use App\Modules\Storefront\Notifications\CustomThemeReviewedNotification;
use App\Modules\Storefront\Notifications\CustomThemeSubmittedNotification;
use App\Modules\Storefront\Services\CustomThemes\CustomThemePackageStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/CustomThemeHelpers.php';
require_once dirname(__DIR__, 2).'/Commerce/CommerceHelpers.php';

/*
| Custom Twig themes end to end (VEN-036 / 036a / 036b, ADM-089 / 089a, MAIL-034,
| SF-ALL-006, decision #121): permissions, upload + validation report, signed preview,
| submit → admin approve / reject (notifications) → publish → storefront render with CSP,
| assets, settings, roll back, switch off, fallback to the built-in theme on render errors,
| tenant isolation.
*/

beforeEach(function (): void {
    Storage::fake(CustomThemePackageStore::DISK);
    Notification::fake();
    $this->tenant = $this->createTenant('ct-'.Str::lower(Str::random(6)), ['name' => 'Twig Shop']);
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->panel = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/store/custom-theme'.$path);
    $this->store = fn (string $path = '/') => $this->tenantUrl($this->tenant, $path);
    $this->upload = function (array $files = []): CustomThemeVersion {
        $path = customThemeZip($files);
        $file = UploadedFile::fake()->createWithContent('theme.zip', (string) file_get_contents($path));
        @unlink($path);
        $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->panel)(), ['package' => $file])->assertOk();

        return CustomThemeVersion::withoutTenantScope()->where('tenant_id', $this->tenant->id)->latest('id')->firstOrFail();
    };
    $this->approve = function (CustomThemeVersion $version): void {
        $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->panel)('/versions/'.$version->id.'/submit'), ['note' => 'First release'])->assertOk();
        $this->flushSession();
        tenancy()->end();
        $this->actingAs($this->admin, 'admin')->postJson($this->centralUrl('/admin/storefront/theme-reviews/'.$version->id.'/decision'), ['decision' => 'approve', 'note' => 'Looks good'])->assertOk();
    };
    $this->admin = commerceAdmin(['storefront.theme-reviews.view', 'storefront.theme-reviews.manage']);
});

it('guards the vendor pages: guest, missing permission, view-only, other store', function (): void {
    $this->get(($this->panel)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo(['dashboard.view']);
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'store.custom-themes.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->getJson(($this->panel)())->assertForbidden();
    $this->getJson(($this->panel)('/docs'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->panel)())->assertOk()->assertSee(__('content::custom_themes.subtitle'))->assertDontSee(__('content::custom_themes.upload.submit'));
    $this->postJson(($this->panel)(), [])->assertForbidden();
    $this->patchJson(($this->panel)('/active?active=1'))->assertForbidden();
    $this->putJson(($this->panel)('/settings'), [])->assertForbidden();

    // Another store's version is not reachable from this store.
    $version = ($this->upload)();
    $other = $this->createTenant('ct-other-'.Str::lower(Str::random(5)));
    $otherOwner = $this->createVendorOwner($other);
    $this->actingAsVendor($otherOwner, $other)->get($this->tenantUrl($other, 'admin/store/custom-theme/versions/'.$version->id))->assertNotFound();
    $this->actingAsVendor($otherOwner, $other)->postJson($this->tenantUrl($other, 'admin/store/custom-theme/versions/'.$version->id.'/submit'))->assertNotFound();
    $this->get($this->tenantUrl($other, 'theme-assets/'.$version->id.'/css/theme.css'))->assertNotFound();
});

it('uploads, reports, previews, reviews, publishes, rolls back and switches off a theme', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);
    $this->get(($this->panel)())->assertOk()->assertSee(__('content::custom_themes.versions.empty_title'));

    // Invalid package: failed version with its report, nothing stored.
    $failed = ($this->upload)(['assets/x.php' => '<?php echo 1;']);
    expect($failed->status)->toBe(CustomThemeVersionStatus::Failed);
    $this->get(($this->panel)('/versions/'.$failed->id))->assertOk()->assertSee('executable / server files are not allowed')->assertSee('data-report-result="failed"', false);
    expect(Storage::disk(CustomThemePackageStore::DISK)->allFiles($failed->directory()))->toBe([]);

    // Valid package: draft, files stored, signed preview.
    $v1 = ($this->upload)();
    expect($v1->status)->toBe(CustomThemeVersionStatus::Draft)
        ->and(Storage::disk(CustomThemePackageStore::DISK)->exists($v1->directory().'/templates/home.twig'))->toBeTrue();
    $this->get(($this->panel)('/versions/'.$v1->id))->assertOk()->assertSee(__('content::custom_themes.report.passed'));

    $preview = $this->get(($this->panel)('/versions/'.$v1->id.'/preview'))->assertRedirect();
    $link = (string) $preview->headers->get('Location');
    expect($link)->toContain('ct_preview='.$v1->id)->toContain('ct_signature=');

    $this->get($link)->assertOk()->assertSee('data-nt-head', false)->assertSee('data-theme-preview-bar', false);
    $this->get(($this->store)('/'))->assertOk()->assertSee('data-nt-head', false); // preview kept in this session
    $this->flushSession();
    tenancy()->end();
    $this->get(($this->store)('/'))->assertOk()->assertDontSee('data-nt-head', false); // other shoppers: built-in theme
    $this->get(str_replace('ct_signature=', 'ct_signature=0', $link))->assertOk()->assertDontSee('data-nt-head', false); // tampered link

    // Submit → admins notified → approve → store owners notified (MAIL-034).
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->panel)('/versions/'.$v1->id.'/submit'), ['note' => 'First release'])->assertOk();
    expect($v1->fresh()->status)->toBe(CustomThemeVersionStatus::PendingReview);
    Notification::assertSentTo($this->admin, CustomThemeSubmittedNotification::class);
    $this->postJson(($this->panel)('/versions/'.$v1->id.'/publish'))->assertUnprocessable();

    $this->flushSession();
    tenancy()->end();
    $this->actingAs($this->admin, 'admin');
    $this->get($this->centralUrl('/admin/storefront/theme-reviews'))->assertOk()->assertSee(__('content::custom_themes.review.subtitle'));
    $this->getJson($this->centralUrl('/admin/storefront/theme-reviews/data?draw=1&start=0&length=25&status=pending_review'))->assertOk()->assertJsonPath('recordsTotal', 1)->assertSee('Twig Shop');
    $this->get($this->centralUrl('/admin/storefront/theme-reviews/'.$v1->id))->assertOk()->assertSee(__('content::custom_themes.review.first_version'))->assertSee('First release')->assertSee('templates/home.twig');
    $this->get($this->centralUrl('/admin/storefront/theme-reviews/'.$v1->id.'/files?path=templates/home.twig'))->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    $this->get($this->centralUrl('/admin/storefront/theme-reviews/'.$v1->id.'/files?path=../../.env'))->assertNotFound();
    $this->get($this->centralUrl('/admin/storefront/theme-reviews/'.$v1->id.'/preview'))->assertRedirect();
    $this->postJson($this->centralUrl('/admin/storefront/theme-reviews/'.$v1->id.'/decision'), ['decision' => 'approve', 'note' => 'Looks good'])->assertOk();
    expect($v1->fresh()->status)->toBe(CustomThemeVersionStatus::Approved)->and($v1->fresh()->reviewed_by_id)->toBe($this->admin->id);
    Notification::assertSentTo($this->owner, CustomThemeReviewedNotification::class, fn (CustomThemeReviewedNotification $notification): bool => $notification->variant === 'approved');

    // Publish → storefront renders the theme with the CSP header and its assets.
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->panel)('/versions/'.$v1->id.'/publish'))->assertOk();
    $theme = CustomTheme::withoutTenantScope()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($theme->live_version_id)->toBe($v1->id)->and($theme->is_active)->toBeTrue();

    $home = $this->get(($this->store)('/'))->assertOk()->assertSee('data-nt-head', false)->assertSee('Twig Shop');
    $csp = (string) $home->headers->get('Content-Security-Policy');
    expect($csp)->toContain("script-src 'self' 'nonce-")->toContain("object-src 'none'")->toContain("frame-ancestors 'self'")
        ->and($home->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    $this->get(($this->store)('/theme-assets/'.$v1->id.'/css/theme.css'))->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8')->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(($this->store)('/theme-assets/'.$v1->id.'/../theme.json'))->assertNotFound();
    $this->get(($this->store)('/theme-assets/'.$v1->id.'/css/missing.css'))->assertNotFound();
    $this->get(($this->store)('/categories'))->assertOk()->assertSee('data-nt-head', false);
    $this->get(($this->store)('/search?q=tee'))->assertOk()->assertSee('data-nt-head', false);
    $this->get(($this->store)('/cart'))->assertOk()->assertSee('data-nt-head', false);
    $this->get(($this->store)('/no-such-page-here'))->assertNotFound()->assertSee('data-nt-head', false);
    $this->get(($this->store)('/checkout'))->assertDontSee('data-nt-head', false); // secure pages stay built-in

    // Settings (VEN-036b): generated form, schema validation, used by the templates.
    $this->get(($this->panel)('/settings'))->assertOk()->assertSee('data-setting="accent"', false);
    $this->putJson(($this->panel)('/settings'), ['settings' => ['accent' => 'red']])->assertUnprocessable()->assertJsonValidationErrors('settings.accent');
    $settings = collect($v1->manifest['settings'])->mapWithKeys(fn (array $setting): array => [$setting['id'] => is_bool($setting['default']) ? ($setting['default'] ? '1' : '0') : (string) $setting['default']])->all();
    $this->putJson(($this->panel)('/settings'), ['settings' => ['accent' => '#123ABC'] + $settings])->assertOk();
    $this->get(($this->store)('/'))->assertOk()->assertSee('#123ABC');

    // A second approved version, then roll back to the first one.
    $v2 = ($this->upload)(['theme.json' => themeManifestJson(['version' => '1.1.0'])]);
    ($this->approve)($v2);
    $this->get($this->centralUrl('/admin/storefront/theme-reviews/'.$v2->id))->assertOk()->assertSee(__('content::custom_themes.review.compared_to', ['name' => 'Starter', 'version' => '1.0.0']))->assertSee('theme.json');
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->panel)('/versions/'.$v2->id.'/publish'))->assertOk();
    expect($theme->fresh()->live_version_id)->toBe($v2->id);
    $this->get(($this->panel)())->assertOk()->assertSee(__('content::custom_themes.actions.rollback'));
    $this->postJson(($this->panel)('/versions/'.$v1->id.'/publish'))->assertOk();
    expect($theme->fresh()->live_version_id)->toBe($v1->id);
    $this->deleteJson(($this->panel)('/versions/'.$v1->id))->assertUnprocessable(); // live version stays

    // Switch the custom theme off: the built-in theme is back.
    $this->patchJson(($this->panel)('/active?active=0'))->assertOk();
    $this->get(($this->store)('/'))->assertOk()->assertDontSee('data-nt-head', false);
    $this->patchJson(($this->panel)('/active?active=1'))->assertOk();
    $this->get(($this->store)('/'))->assertOk()->assertSee('data-nt-head', false);
});

it('rejects with a required note and notifies the store', function (): void {
    $viewer = commerceAdmin(['storefront.theme-reviews.view']);
    $nobody = commerceAdmin([]);
    $version = ($this->upload)();
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->panel)('/versions/'.$version->id.'/submit'))->assertOk();

    $this->flushSession();
    tenancy()->end();
    $this->actingAs($viewer, 'admin')
        ->postJson($this->centralUrl('/admin/storefront/theme-reviews/'.$version->id.'/decision'), ['decision' => 'approve'])->assertForbidden();
    $this->actingAs($nobody, 'admin')->getJson($this->centralUrl('/admin/storefront/theme-reviews'))->assertForbidden();

    $this->actingAs($this->admin, 'admin');
    $this->postJson($this->centralUrl('/admin/storefront/theme-reviews/'.$version->id.'/decision'), ['decision' => 'reject'])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->postJson($this->centralUrl('/admin/storefront/theme-reviews/'.$version->id.'/decision'), ['decision' => 'reject', 'note' => 'The cart page is empty on phones.'])->assertOk();

    expect($version->fresh()->status)->toBe(CustomThemeVersionStatus::Rejected);
    Notification::assertSentTo($this->owner, CustomThemeReviewedNotification::class, fn (CustomThemeReviewedNotification $notification): bool => $notification->variant === 'rejected' && $notification->replace['note'] === 'The cart page is empty on phones.');

    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->panel)('/versions/'.$version->id))->assertOk()->assertSee('The cart page is empty on phones.');
    $this->postJson(($this->panel)('/versions/'.$version->id.'/publish'))->assertUnprocessable();
    $this->postJson(($this->panel)('/versions/'.$version->id.'/submit'))->assertOk(); // a rejected version may be resubmitted
});

it('falls back to the built-in theme when the live theme fails and tells the owner', function (): void {
    $version = ($this->upload)();
    ($this->approve)($version);
    $this->actingAsVendor($this->owner, $this->tenant)->postJson(($this->panel)('/versions/'.$version->id.'/publish'))->assertOk();

    Storage::disk(CustomThemePackageStore::DISK)->put($version->directory().'/templates/home.twig', '{% extends "layout.twig" %}{% block content %}{{ include("missing.twig") }}{% endblock %}');
    app(CustomThemePackageStore::class)->clearCompiled($version);

    $this->get(($this->store)('/'))->assertOk()->assertDontSee('data-nt-head', false);

    $theme = CustomTheme::withoutTenantScope()->where('tenant_id', $this->tenant->id)->firstOrFail();
    expect($theme->last_render_error)->toContain('missing.twig');
    Notification::assertSentToTimes($this->owner, CustomThemeRenderFailedNotification::class, 1);

    $this->get(($this->store)('/'))->assertOk(); // throttled: still one notification
    Notification::assertSentToTimes($this->owner, CustomThemeRenderFailedNotification::class, 1);
    $this->get(($this->panel)())->assertOk()->assertSee('data-render-error', false);
});

it('serves the starter kit, the developer guide and the help article', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant);

    $kit = $this->get(($this->panel)('/starter-kit'))->assertOk()->assertHeader('Content-Type', 'application/zip');
    expect($kit->baseResponse->headers->get('Content-Disposition'))->toContain('nogrgr-starter-theme.zip');

    $this->get(($this->panel)('/docs'))->assertOk()
        ->assertSee(__('content::custom_theme_docs.title'))
        ->assertSee('product.price')->assertSee('cart_page.lines[].quantity')->assertSee('store.dir')
        ->assertSee(__('content::custom_themes.docs.limit_names.render_max_ms'));

    $this->get($this->tenantUrl($this->tenant, 'admin/help/articles/custom-twig-themes'))->assertOk()
        ->assertSee('store/custom-theme/docs', false);
});
