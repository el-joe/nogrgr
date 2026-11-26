<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Models\City;
use App\Modules\Localization\Models\Country;
use App\Modules\Platform\Enums\ComplianceAccountType;
use App\Modules\Platform\Enums\ComplianceDocumentStatus;
use App\Modules\Platform\Enums\ComplianceDocumentType;
use App\Modules\Platform\Enums\ComplianceStatus;
use App\Modules\Platform\Models\ComplianceDocument;
use App\Modules\Platform\Models\ComplianceProfile;
use App\Modules\Platform\Notifications\ComplianceSubmittedNotification;
use App\Modules\Platform\Services\ComplianceCenterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| VEN-084 Vendor › Settings › Compliance center: permissions, draft save with private
| uploads, validation, submit (admins notified), read-only while in review / verified,
| re-submit after rejection, document download / removal and tenant isolation.
*/

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('central');

    $this->store = $this->createTenant('kyc-'.Str::lower(Str::random(6)), ['name' => 'Megz Store']);
    $this->owner = $this->createVendorOwner($this->store);
    $this->other = vendorsTenant(['name' => 'Other Store']);
    $this->country = Country::factory()->iso('SA')->create();
    $this->city = City::factory()->create(['country_id' => $this->country->id, 'name' => ['en' => 'Riyadh', 'ar' => 'الرياض']]);
    $this->url = fn (string $path = ''): string => $this->tenantUrl($this->store, 'admin/settings/compliance'.($path === '' ? '' : '/'.$path));
    $this->payload = fn (array $overrides = []): array => array_merge([
        'owner_name' => 'Mostafa Gamal',
        'owner_id_number' => '1023456789',
        'owner_id_expires_at' => now()->addYears(3)->toDateString(),
        'owner_birth_date' => '1990-05-04',
        'owner_phone' => '0550102030',
        'owner_phone_country' => 'sa',
        'account_type' => ComplianceAccountType::Company->value,
        'legal_name' => 'Megz Trading Est.',
        'registration_number' => '1010998221',
        'country_id' => $this->country->id,
        'city_id' => $this->city->id,
        'address' => 'King Fahd Road, Riyadh',
        'company_email' => 'kyc@megz.test',
        'bank_name' => 'Al Rajhi Bank',
        'iban' => 'SA03 8000 0000 6080 1016 7519',
        'account_name' => 'Megz Trading Est.',
    ], $overrides);
    $this->files = fn (): array => [
        'documents' => [
            'national_id' => UploadedFile::fake()->create('id.pdf', 120, 'application/pdf'),
            'commercial_registration' => UploadedFile::fake()->image('cr.png'),
        ],
    ];
    $this->profile = fn (): ?ComplianceProfile => ComplianceProfile::withoutTenantScope()->where('tenant_id', $this->store->id)->first();
});

function complianceCenterStaff(object $test, array $permissions): VendorStaff
{
    return $test->store->run(function () use ($permissions): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo(['dashboard.view', ...$permissions]);

        return $staff;
    });
}

it('redirects guests and needs settings.compliance.view / manage', function (): void {
    $this->get(($this->url)())->assertRedirect();

    $this->actingAsVendor(complianceCenterStaff($this, []), $this->store);
    $this->getJson(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('cities-by-country/'.$this->country->id))->assertForbidden();

    $this->actingAsVendor(complianceCenterStaff($this, ['settings.compliance.view']), $this->store);
    $this->get(($this->url)())
        ->assertOk()
        ->assertSee(__('platform::vendor_compliance.title'))
        ->assertSee(__('platform::vendor_compliance.banner.readonly'))
        ->assertDontSee(__('platform::vendor_compliance.buttons.submit'));
    $this->postJson(($this->url)(), ($this->payload)())->assertForbidden();
    $this->postJson(($this->url)('submit'), ($this->payload)())->assertForbidden();
    $this->deleteJson(($this->url)('documents/1'))->assertForbidden();

    expect(($this->profile)())->toBeNull();
});

it('renders the empty compliance center for the owner', function (): void {
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)())
        ->assertOk()
        ->assertSee(__('platform::vendor_compliance.banner.draft_title'))
        ->assertSee(__('platform::vendor_compliance.buttons.submit'))
        ->assertSee('data-page="vendor-compliance"', false)
        ->assertSee('Saudi Arabia')
        // QA-P8: the step bar scrolls inside a positioned wrapper so it never widens the page on phones.
        ->assertSee('class="relative overflow-x-auto', false)
        ->assertSee('class="grid grid-cols-1 gap-6" data-compliance', false);
});

it('saves a draft with private uploads pinned to the current store', function (): void {
    $admin = vendorsAdmin();
    $this->actingAsVendor($this->owner, $this->store);

    $this->post(($this->url)(), ($this->payload)(['step' => 'bank']) + ($this->files)(), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('redirect', fn (string $url): bool => str_contains($url, 'step=bank'));

    $profile = ($this->profile)();
    expect($profile)->not->toBeNull()
        ->and($profile->status)->toBe(ComplianceStatus::Draft)
        ->and($profile->owner_phone)->toBe('+966550102030')
        ->and($profile->iban)->toBe('SA0380000000608010167519')
        ->and($profile->getRawOriginal('iban'))->not->toBe('SA0380000000608010167519')
        ->and($profile->getRawOriginal('owner_id_number'))->not->toBe('1023456789')
        ->and($profile->city_id)->toBe($this->city->id)
        ->and($profile->submitted_at)->toBeNull()
        ->and($profile->documents)->toHaveCount(2);

    $national = $profile->documents->firstWhere('type', ComplianceDocumentType::NationalId);
    expect($national->disk)->toBe('central')
        ->and($national->document_number)->toBe('1023456789')
        ->and($national->holder_name)->toBe('Mostafa Gamal')
        ->and($national->status)->toBe(ComplianceDocumentStatus::Review);
    Storage::disk('central')->assertExists($national->path);
    expect(ComplianceProfile::withoutTenantScope()->where('tenant_id', $this->other->id)->exists())->toBeFalse();

    // A draft never reaches the admin review queue.
    $this->actingAs($admin, 'admin');
    $this->getJson($this->centralUrl('/admin/vendors/compliance/data?draw=1&start=0&length=25'))->assertOk()->assertJsonPath('recordsTotal', 0);
    Notification::assertNothingSent();
});

it('replaces a document of the same type and deletes the old file', function (): void {
    $this->actingAsVendor($this->owner, $this->store);
    $this->post(($this->url)(), ($this->payload)() + ($this->files)(), ['Accept' => 'application/json'])->assertOk();
    $old = ($this->profile)()->documents->firstWhere('type', ComplianceDocumentType::NationalId)->path;

    $this->post(($this->url)(), ($this->payload)() + ['documents' => ['national_id' => UploadedFile::fake()->create('id-new.pdf', 50, 'application/pdf')]], ['Accept' => 'application/json'])->assertOk();

    $documents = ($this->profile)()->documents->where('type', ComplianceDocumentType::NationalId);
    expect($documents)->toHaveCount(1)->and($documents->first()->original_name)->toBe('id-new.pdf');
    Storage::disk('central')->assertMissing($old);
});

it('validates the fields and files', function (): void {
    $this->actingAsVendor($this->owner, $this->store);
    $foreignCity = City::factory()->create(['country_id' => Country::factory()->iso('EG')->create()->id]);

    $this->post(($this->url)(), ($this->payload)([
        'iban' => 'SA03 8000 0000 6080 1016 7518',
        'owner_phone' => '12',
        'company_email' => 'not-an-email',
        'city_id' => $foreignCity->id,
        'account_type' => 'partnership',
        'documents' => ['national_id' => UploadedFile::fake()->create('id.exe', 10, 'application/x-msdownload')],
    ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['iban', 'owner_phone', 'company_email', 'city_id', 'account_type', 'documents.national_id']);

    $this->post(($this->url)(), ['documents' => ['national_id' => UploadedFile::fake()->create('id.pdf', ComplianceCenterService::MAX_FILE_KB + 1, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['documents.national_id']);

    expect(($this->profile)())->toBeNull();
});

it('refuses to submit an incomplete profile and keeps the draft', function (): void {
    $this->actingAsVendor($this->owner, $this->store);

    $this->post(($this->url)('submit'), ['owner_name' => 'Mostafa Gamal', 'account_type' => 'company'], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['owner_id_number', 'owner_phone', 'documents.national_id', 'legal_name', 'documents.commercial_registration', 'iban', 'accept_terms']);

    expect(($this->profile)()->status)->toBe(ComplianceStatus::Draft)
        ->and(($this->profile)()->owner_name)->toBe('Mostafa Gamal');
    Notification::assertNothingSent();
});

it('submits for review and notifies the admins who decide KYC', function (): void {
    $reviewer = vendorsAdmin(['vendors.kyc.view', 'vendors.kyc.manage']);
    $viewer = vendorsAdmin(['vendors.kyc.view']);
    $this->actingAsVendor($this->owner, $this->store);

    $this->post(($this->url)('submit'), ($this->payload)(['accept_terms' => '1']) + ($this->files)(), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.status', 'needs_review');

    $profile = ($this->profile)();
    expect($profile->status)->toBe(ComplianceStatus::NeedsReview)
        ->and($profile->submitted_at)->not->toBeNull()
        ->and($profile->terms_accepted_at)->not->toBeNull()
        ->and($profile->terms_version)->not->toBeNull()
        ->and($profile->terms_accepted_ip)->toBe('127.0.0.1');

    Notification::assertSentTo($reviewer, ComplianceSubmittedNotification::class, function (ComplianceSubmittedNotification $notification, array $channels) use ($profile): bool {
        return $notification->profileId === $profile->id && $notification->storeName === 'Megz Store' && ! $notification->resubmitted && $notification->event() === 'compliance_submitted';
    });
    Notification::assertNotSentTo($viewer, ComplianceSubmittedNotification::class);

    // Under review: read-only, no more changes.
    $this->get(($this->url)())->assertOk()
        ->assertSee(__('platform::vendor_compliance.banner.needs_review_title'))
        ->assertDontSee(__('platform::vendor_compliance.buttons.save'));
    $this->postJson(($this->url)(), ($this->payload)(['owner_name' => 'Changed']))->assertUnprocessable()->assertJsonPath('message', __('platform::vendor_compliance.errors.locked'));
    expect(($this->profile)()->owner_name)->toBe('Mostafa Gamal');
});

it('allows an individual seller without a commercial registration', function (): void {
    $this->actingAsVendor($this->owner, $this->store);

    $this->post(($this->url)('submit'), ($this->payload)(['account_type' => 'individual', 'legal_name' => null, 'registration_number' => null, 'accept_terms' => '1']) + ['documents' => ['national_id' => UploadedFile::fake()->create('id.pdf', 120, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertOk();

    $profile = ($this->profile)();
    expect($profile->status)->toBe(ComplianceStatus::NeedsReview)
        ->and($profile->account_type)->toBe(ComplianceAccountType::Individual)
        ->and($profile->legal_name)->toBe('Mostafa Gamal')
        ->and($profile->registration_number)->toBeNull();
});

it('keeps a verified profile read-only', function (): void {
    $profile = ComplianceProfile::factory()->create(['tenant_id' => $this->store->id, 'status' => ComplianceStatus::Verified, 'reviewed_at' => now()]);
    $document = ComplianceDocument::factory()->create(['tenant_id' => $this->store->id, 'compliance_profile_id' => $profile->id, 'disk' => 'central', 'path' => 'compliance/x.pdf']);
    Storage::disk('central')->put('compliance/x.pdf', '%PDF-1.4');
    $this->actingAsVendor($this->owner, $this->store);

    $this->get(($this->url)())->assertOk()
        ->assertSee(__('platform::vendor_compliance.banner.verified_title'))
        ->assertDontSee(__('platform::vendor_compliance.buttons.submit'))
        ->assertSee((string) $document->original_name)
        ->assertDontSee('data-confirm-method="DELETE"', false);
    $this->postJson(($this->url)('submit'), ($this->payload)(['accept_terms' => '1']))->assertUnprocessable();
    $this->deleteJson(($this->url)('documents/'.$document->id))->assertUnprocessable();

    expect($profile->fresh()->status)->toBe(ComplianceStatus::Verified);
    Storage::disk('central')->assertExists('compliance/x.pdf');
    Notification::assertNothingSent();
});

it('shows the rejection note and re-submits', function (): void {
    $reviewer = vendorsAdmin(['vendors.kyc.manage']);
    $this->actingAsVendor($this->owner, $this->store);
    $this->post(($this->url)('submit'), ($this->payload)(['accept_terms' => '1']) + ($this->files)(), ['Accept' => 'application/json'])->assertOk();
    $profile = ($this->profile)();
    $profile->update(['status' => ComplianceStatus::Rejected, 'decision_note' => 'IBAN name does not match the company name.']);
    Notification::fake();

    $this->get(($this->url)())->assertOk()
        ->assertSee(__('platform::vendor_compliance.banner.rejected_title'))
        ->assertSee('IBAN name does not match the company name.')
        ->assertSee(__('platform::vendor_compliance.buttons.resubmit'));

    $this->post(($this->url)('submit'), ($this->payload)(['account_name' => 'Megz Trading Est.']) + ['documents' => ['bank_letter' => UploadedFile::fake()->create('letter.pdf', 40, 'application/pdf')]], ['Accept' => 'application/json'])
        ->assertOk();

    expect(($this->profile)()->status)->toBe(ComplianceStatus::NeedsReview)
        ->and(($this->profile)()->documents->pluck('type')->all())->toContain(ComplianceDocumentType::BankLetter);
    Notification::assertSentTo($reviewer, ComplianceSubmittedNotification::class, fn (ComplianceSubmittedNotification $notification): bool => $notification->resubmitted && $notification->event() === 'compliance_resubmitted');
});

it('streams and removes only the store\'s own documents', function (): void {
    $this->actingAsVendor($this->owner, $this->store);
    $this->post(($this->url)(), ($this->payload)() + ($this->files)(), ['Accept' => 'application/json'])->assertOk();
    $document = ($this->profile)()->documents->firstWhere('type', ComplianceDocumentType::NationalId);

    $foreignProfile = ComplianceProfile::factory()->create(['tenant_id' => $this->other->id, 'status' => ComplianceStatus::NeedsAction]);
    $foreign = ComplianceDocument::factory()->create(['tenant_id' => $this->other->id, 'compliance_profile_id' => $foreignProfile->id, 'disk' => 'central', 'path' => 'compliance/foreign.pdf']);
    Storage::disk('central')->put('compliance/foreign.pdf', '%PDF-1.4');

    $this->get(($this->url)('documents/'.$document->id))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get(($this->url)('documents/'.$document->id.'?download=1'))->assertOk()->assertDownload('id.pdf');
    $this->get(($this->url)('documents/'.$foreign->id))->assertNotFound();
    $this->deleteJson(($this->url)('documents/'.$foreign->id))->assertNotFound();
    Storage::disk('central')->assertExists('compliance/foreign.pdf');

    $this->deleteJson(($this->url)('documents/'.$document->id))->assertOk()->assertJsonPath('success', true);
    expect(ComplianceDocument::withoutTenantScope()->find($document->id))->toBeNull();
    Storage::disk('central')->assertMissing($document->path);
});

it('lists the cities of an active country for Select2', function (): void {
    $this->actingAsVendor(complianceCenterStaff($this, ['settings.compliance.view']), $this->store);
    $inactive = Country::factory()->inactive()->create();

    $this->getJson(($this->url)('cities-by-country/'.$this->country->id.'?q=riy'))
        ->assertOk()
        ->assertJsonPath('results.0.id', (string) $this->city->id)
        ->assertJsonPath('results.0.text', 'Riyadh')
        ->assertJsonPath('pagination.more', false);
    $this->getJson(($this->url)('cities-by-country/'.$inactive->id))->assertNotFound();
});
