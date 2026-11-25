<?php

use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Enums\ComplianceDocumentStatus;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Enums\ComplianceRisk;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Models\ComplianceDocument;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Notifications\ComplianceActionNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| Admin › Vendors › Compliance / KYC (ADM-044 / 044a): permissions, queue + filters,
| review page, private document streaming, decisions and per-document flags.
*/

function complianceProfile(array $attributes = [], ?Tenant $tenant = null): ComplianceProfile
{
    $tenant ??= vendorsTenant();

    return ComplianceProfile::factory()->create(['tenant_id' => $tenant->id, ...$attributes]);
}

function complianceDocument(ComplianceProfile $profile, array $attributes = []): ComplianceDocument
{
    return ComplianceDocument::factory()->create(['tenant_id' => $profile->tenant_id, 'compliance_profile_id' => $profile->id, ...$attributes]);
}

it('protects every compliance endpoint', function (): void {
    $profile = complianceProfile();
    $document = complianceDocument($profile);
    $base = "/admin/vendors/compliance/{$profile->id}";

    foreach (['/admin/vendors/compliance', '/admin/vendors/compliance/data', $base, "{$base}/documents/{$document->id}"] as $path) {
        $this->get($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));
    }
    $this->post($this->centralUrl("{$base}/decision"))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $this->getJson($this->centralUrl('/admin/vendors/compliance'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/vendors/compliance/data'))->assertForbidden();
    $this->getJson($this->centralUrl($base))->assertForbidden();
    $this->getJson($this->centralUrl("{$base}/documents/{$document->id}"))->assertForbidden();
    $this->postJson($this->centralUrl("{$base}/decision"), ['status' => 'verified'])->assertForbidden();
    $this->patchJson($this->centralUrl("{$base}/documents/{$document->id}"), ['status' => 'verified'])->assertForbidden();
});

it('lets kyc viewers review but not decide', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.kyc.view']), 'admin');
    $profile = complianceProfile();
    $document = complianceDocument($profile);

    $this->get($this->centralUrl("/admin/vendors/compliance/{$profile->id}"))
        ->assertOk()
        ->assertDontSee('data-decision="verified"', false)
        ->assertDontSee(route('admin.vendors.compliance.document.status', ['complianceProfile' => $profile->id, 'document' => $document->id]), false);

    $this->postJson($this->centralUrl("/admin/vendors/compliance/{$profile->id}/decision"), ['status' => 'verified'])->assertForbidden();
    $this->patchJson($this->centralUrl("/admin/vendors/compliance/{$profile->id}/documents/{$document->id}"), ['status' => 'verified'])->assertForbidden();
});

it('renders the queue with status tabs and counts', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    complianceProfile();
    complianceProfile(['status' => ComplianceStatus::Verified]);

    $this->get($this->centralUrl('/admin/vendors/compliance'))
        ->assertOk()
        ->assertSee(ComplianceStatus::NeedsAction->label())
        ->assertSee(route('admin.vendors.compliance.data', ['status' => 'needs_review']), false);

    $this->get($this->centralUrl('/admin/vendors/compliance?status=verified'))
        ->assertOk()
        ->assertSee(route('admin.vendors.compliance.data', ['status' => 'verified']), false);
});

it('lists profiles as JSON with status, document, country and search filters and no secrets', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $saudi = Country::factory()->iso('SA')->create();
    $rose = complianceProfile(['risk' => ComplianceRisk::Medium, 'decision_note' => 'secret note'], vendorsTenant(['name' => 'Desert Rose', 'default_country_id' => $saudi->id]));
    complianceDocument($rose, ['type' => ComplianceDocumentType::CommercialRegistration, 'document_number' => '1010773122']);
    complianceDocument($rose, ['type' => ComplianceDocumentType::NationalId]);
    complianceProfile([], vendorsTenant(['name' => 'GearUp']));
    complianceProfile(['status' => ComplianceStatus::Verified], vendorsTenant(['name' => 'Kids Land']));

    $url = fn (string $query = '') => $this->centralUrl('/admin/vendors/compliance/data?draw=1&start=0&length=10'.$query);

    $all = $this->getJson($url())->assertOk()->json();
    expect($all['recordsTotal'])->toBe(3);

    $row = collect($all['data'])->firstWhere('id', $rose->id);
    expect(array_keys($row))->toEqualCanonicalizing(['id', 'store', 'documents', 'submitted', 'risk', 'status', 'actions'])
        ->and($row['store'])->toContain('Desert Rose')
        ->and($row['documents'])->toContain(ComplianceDocumentType::CommercialRegistration->shortLabel())
        ->and($row['risk'])->toContain(ComplianceRisk::Medium->label())
        ->and($row['actions'])->toContain(route('admin.vendors.compliance.show', $rose->id));

    $json = json_encode($all);
    expect($json)->not->toContain('SA0380000000608010167519')
        ->and($json)->not->toContain('1010773122')
        ->and($json)->not->toContain('secret note');

    expect($this->getJson($url('&status=needs_review'))->json('recordsFiltered'))->toBe(2)
        ->and($this->getJson($url('&document=national_id'))->json('data.0.id'))->toBe($rose->id)
        ->and($this->getJson($url("&country_id={$saudi->id}"))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url('&search[value]=GearUp'))->json('recordsFiltered'))->toBe(1);
});

it('shows the review page with masked data, previews and empty states', function (): void {
    Storage::fake('local');
    $this->actingAs(vendorsAdmin(), 'admin');
    $profile = complianceProfile([], vendorsTenant(['name' => 'Desert Rose']));
    $image = complianceDocument($profile, ['type' => ComplianceDocumentType::NationalId, 'path' => 'kyc/id.png', 'mime_type' => 'image/png', 'document_number' => '1234567842']);
    $pdf = complianceDocument($profile, ['path' => 'kyc/cr.pdf', 'expires_at' => now()->subDay()->toDateString()]);
    complianceDocument($profile, ['type' => ComplianceDocumentType::TaxCertificate, 'path' => 'kyc/gone.pdf']);
    Storage::disk('local')->put('kyc/id.png', 'png-bytes');
    Storage::disk('local')->put('kyc/cr.pdf', '%PDF-1.4');

    $documentUrl = fn (ComplianceDocument $document) => route('admin.vendors.compliance.document', ['complianceProfile' => $profile->id, 'document' => $document->id]);

    $this->get($this->centralUrl("/admin/vendors/compliance/{$profile->id}"))
        ->assertOk()
        ->assertSee('Desert Rose')
        ->assertSee('<img src="'.$documentUrl($image).'"', false)
        ->assertSee('<iframe src="'.$documentUrl($pdf).'"', false)
        ->assertSee(__('platform::compliance.document.missing_title'))
        ->assertSee(__('platform::compliance.document.expired'))
        ->assertSee('1•••••••42')
        ->assertSee('SA03 8000 ••• 7519')
        ->assertDontSee('1234567842')
        ->assertDontSee('SA0380000000608010167519')
        ->assertSee('data-decision="verified"', false);

    $empty = complianceProfile(['bank_name' => null, 'iban' => null, 'account_name' => null]);
    $this->get($this->centralUrl("/admin/vendors/compliance/{$empty->id}"))
        ->assertOk()
        ->assertSee(__('platform::compliance.no_documents_title'))
        ->assertSee(__('platform::compliance.bank.empty'));
});

it('streams documents only from the private disk of their own profile', function (): void {
    Storage::fake('local');
    $this->actingAs(vendorsAdmin(['vendors.kyc.view']), 'admin');
    $profile = complianceProfile();
    $document = complianceDocument($profile, ['path' => 'kyc/cr.pdf', 'original_name' => 'cr.pdf']);
    $missing = complianceDocument($profile, ['path' => 'kyc/missing.pdf']);
    $other = complianceDocument(complianceProfile(), ['path' => 'kyc/other.pdf']);
    Storage::disk('local')->put('kyc/cr.pdf', '%PDF-1.4 test');
    Storage::disk('local')->put('kyc/other.pdf', '%PDF-1.4 other');

    $base = "/admin/vendors/compliance/{$profile->id}/documents";

    $inline = $this->get($this->centralUrl("{$base}/{$document->id}"))->assertOk();
    expect($inline->streamedContent())->toBe('%PDF-1.4 test')
        ->and($inline->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($inline->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    $download = $this->get($this->centralUrl("{$base}/{$document->id}?download=1"))->assertOk();
    expect($download->headers->get('Content-Disposition'))->toStartWith('attachment');

    $this->get($this->centralUrl("{$base}/{$other->id}"))->assertNotFound();
    $this->get($this->centralUrl("{$base}/{$missing->id}"))->assertNotFound();
});

it('verifies a store, marks every document verified and e-mails the owner', function (): void {
    Notification::fake();
    $admin = vendorsAdmin(['vendors.kyc.view', 'vendors.kyc.manage']);
    $this->actingAs($admin, 'admin');
    $tenant = vendorsTenant(['owner_email' => 'reem@desertrose.sa']);
    $profile = complianceProfile([], $tenant);
    $document = complianceDocument($profile);

    $this->postJson($this->centralUrl("/admin/vendors/compliance/{$profile->id}/decision"), ['status' => 'verified', 'note' => ''])
        ->assertOk()
        ->assertJson(['success' => true]);

    $profile->refresh();
    expect($profile->status)->toBe(ComplianceStatus::Verified)
        ->and($profile->reviewed_by_admin_id)->toBe($admin->id)
        ->and($profile->decision_note)->toBeNull()
        ->and($document->refresh()->status)->toBe(ComplianceDocumentStatus::Verified);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        ComplianceActionNotification::class,
        fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'reem@desertrose.sa',
    );
});

it('requires a note for needs action and reject', function (string $status): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $profile = complianceProfile();

    $this->postJson($this->centralUrl("/admin/vendors/compliance/{$profile->id}/decision"), ['status' => $status, 'note' => '  '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['note' => __('platform::messages.note_required')]);

    expect($profile->refresh()->status)->toBe(ComplianceStatus::NeedsReview);
})->with(['needs_action', 'rejected']);

it('asks the store for action with a note', function (): void {
    Notification::fake();
    $this->actingAs(vendorsAdmin(), 'admin');
    $profile = complianceProfile();

    $this->postJson($this->centralUrl("/admin/vendors/compliance/{$profile->id}/decision"), ['status' => 'needs_action', 'note' => 'The CR copy is blurry.'])
        ->assertOk();

    expect($profile->refresh()->status)->toBe(ComplianceStatus::NeedsAction)
        ->and($profile->decision_note)->toBe('The CR copy is blurry.');
    Notification::assertSentTimes(ComplianceActionNotification::class, 1);
});

it('validates decisions and refuses invalid transitions', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $profile = complianceProfile(['status' => ComplianceStatus::Verified]);

    $this->postJson($this->centralUrl("/admin/vendors/compliance/{$profile->id}/decision"), ['status' => 'needs_review'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->postJson($this->centralUrl("/admin/vendors/compliance/{$profile->id}/decision"), ['status' => 'verified'])
        ->assertUnprocessable()
        ->assertJson(['success' => false]);
});

it('flags a single document', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $profile = complianceProfile();
    $document = complianceDocument($profile);
    $other = complianceDocument(complianceProfile());
    $base = "/admin/vendors/compliance/{$profile->id}/documents";

    $this->patchJson($this->centralUrl("{$base}/{$document->id}"), ['status' => 'needs_action'])->assertOk();
    expect($document->refresh()->status)->toBe(ComplianceDocumentStatus::NeedsAction)
        ->and($profile->refresh()->status)->toBe(ComplianceStatus::NeedsReview);

    $this->patchJson($this->centralUrl("{$base}/{$document->id}"), ['status' => 'review'])->assertJsonValidationErrors('status');
    $this->patchJson($this->centralUrl("{$base}/{$other->id}"), ['status' => 'verified'])->assertNotFound();
    expect($other->refresh()->status)->toBe(ComplianceDocumentStatus::Review);
});
