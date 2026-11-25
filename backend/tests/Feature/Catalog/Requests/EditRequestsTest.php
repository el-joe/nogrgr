<?php

use App\Modules\Catalog\DTOs\Requests\EditRequestSubmissionData;
use App\Modules\Catalog\Enums\EditRequestStatus;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOverride;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Notifications\RequestUpdateNotification;
use App\Modules\Catalog\Services\Requests\EditRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/RequestHelpers.php';

/*
| Requests center › Product edit requests (ADM-018a/b): vendor submit API (diff), list,
| detail, approve → tenant override (central product unchanged), reject needs a reason.
*/

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('central');
    Storage::fake('public');
    [$this->tenant, $this->owner] = requestsStore();
    $this->product = Product::factory()->create(['name' => ['en' => 'Wireless Earbuds Pro', 'ar' => 'سماعات لاسلكية برو']]);
});

function submitEditRequest(object $test, array $values, array $images = []): EditRequest
{
    return app(EditRequestService::class)->submit(new EditRequestSubmissionData(
        tenantId: $test->tenant->id,
        productId: $test->product->id,
        variantId: null,
        values: $values,
        images: $images,
        vendorNote: 'Our customers search in Arabic.',
        submittedBy: 'Megz Owner',
    ));
}

it('builds the field diff on submit and keeps only real changes', function (): void {
    $request = submitEditRequest($this, [
        'name' => ['ar' => 'سماعات ميغز برو', 'en' => 'Wireless Earbuds Pro'],
        'summary' => ['en' => 'Noise-cancelling earbuds with free tips.'],
        'unknown' => ['en' => 'ignored'],
    ], [UploadedFile::fake()->image('front.jpg')]);

    expect($request->status)->toBe(EditRequestStatus::Pending)
        ->and(collect($request->changes)->map(fn (array $change): string => $change['field'].':'.$change['locale'])->all())->toBe(['name:ar', 'summary:en', 'images:'])
        ->and($request->changes[0]['current'])->toBe('سماعات لاسلكية برو')
        ->and($request->files)->toHaveCount(1);
    Storage::disk('central')->assertExists($request->files[0]['path']);
    Storage::disk('local')->assertMissing($request->files[0]['path']);

    expect(fn () => submitEditRequest($this, ['summary' => ['en' => 'Another']]))->toThrow(RequestRuleException::class);
});

it('refuses a submission without changes', function (): void {
    expect(fn () => submitEditRequest($this, ['name' => ['en' => 'Wireless Earbuds Pro']]))->toThrow(RequestRuleException::class);
});

it('lists edit requests and renders the diff page', function (): void {
    $request = submitEditRequest($this, ['name' => ['ar' => 'سماعات ميغز برو']]);
    EditRequest::factory()->create(['status' => EditRequestStatus::Rejected]);
    $this->actingAs(catalogAdmin(['requests.edits.view']), 'admin');

    expect($this->getJson($this->centralUrl('/admin/requests/edits/data?draw=1&start=0&length=25'))->assertOk()->json('recordsFiltered'))->toBe(2)
        ->and($this->getJson($this->centralUrl('/admin/requests/edits/data?draw=1&start=0&length=25&status=pending'))->json('data.0.request'))->toContain($request->reference());

    $this->get($this->centralUrl("/admin/requests/edits/{$request->id}"))
        ->assertOk()
        ->assertSee('سماعات لاسلكية برو')
        ->assertSee('سماعات ميغز برو')
        ->assertDontSee('edit-reject-modal', false);
});

it('approves an edit request into the store tenant override table without touching the central product', function (): void {
    Notification::fake();
    $this->tenant = $this->createTenant('megz', ['owner_id' => $this->owner->id, 'owner_name' => $this->owner->name, 'owner_email' => $this->owner->email]);
    $request = submitEditRequest($this, ['name' => ['ar' => 'سماعات ميغز برو'], 'weight_grams' => ['' => '250']], [UploadedFile::fake()->image('front.jpg')]);
    $this->actingAs(catalogAdmin(['requests.edits.manage']), 'admin');

    $this->postJson($this->centralUrl("/admin/requests/edits/{$request->id}/approve"))->assertOk();

    /** @var ProductOverride $override */
    $override = $this->tenant->run(fn () => ProductOverride::query()->where('product_id', $this->product->id)->firstOrFail());

    expect($request->refresh()->status)->toBe(EditRequestStatus::Approved)
        ->and($override->getTranslation('name', 'ar', false))->toBe('سماعات ميغز برو')
        ->and($override->getTranslation('name', 'en', false))->toBe('')
        ->and($override->weight_grams)->toBe(250)
        ->and($override->approvals['name:ar']['central_value'])->toBe('سماعات لاسلكية برو')
        ->and($override->approvals['name:ar']['edit_request_id'])->toBe($request->id)
        ->and($override->images)->toHaveCount(1)
        ->and($this->product->refresh()->getTranslation('name', 'ar'))->toBe('سماعات لاسلكية برو');
    Storage::disk('public')->assertExists($override->images[0]);
    Notification::assertSentTo($this->owner, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'edit_approved');

    $this->postJson($this->centralUrl("/admin/requests/edits/{$request->id}/approve"))->assertUnprocessable();
});

it('requires a reason to reject', function (): void {
    Notification::fake();
    $request = submitEditRequest($this, ['name' => ['ar' => 'سماعات ميغز برو']]);
    $this->actingAs(catalogAdmin(['requests.edits.manage']), 'admin');

    $this->postJson($this->centralUrl("/admin/requests/edits/{$request->id}/reject"), ['reason' => ''])->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect($request->refresh()->status)->toBe(EditRequestStatus::Pending);

    $this->postJson($this->centralUrl("/admin/requests/edits/{$request->id}/reject"), ['reason' => 'Brand names are not allowed in titles.'])->assertOk();

    expect($request->refresh()->status)->toBe(EditRequestStatus::Rejected)
        ->and($request->reason)->toBe('Brand names are not allowed in titles.');
    Notification::assertSentTo($this->owner, RequestUpdateNotification::class, fn (RequestUpdateNotification $notification): bool => $notification->eventName === 'edit_rejected');
});

it('guards the review actions by permission', function (): void {
    $request = submitEditRequest($this, ['name' => ['ar' => 'سماعات ميغز برو']], [UploadedFile::fake()->image('front.jpg')]);

    $this->postJson($this->centralUrl("/admin/requests/edits/{$request->id}/approve"))->assertUnauthorized();

    $this->actingAs(catalogAdmin(['requests.edits.view']), 'admin');
    $this->postJson($this->centralUrl("/admin/requests/edits/{$request->id}/approve"))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/requests/edits/{$request->id}/reject"), ['reason' => 'No'])->assertForbidden();
    $this->get($this->centralUrl("/admin/requests/edits/{$request->id}/files/0"))->assertOk();
    $this->get($this->centralUrl("/admin/requests/edits/{$request->id}/files/5"))->assertNotFound();
});

it('stores vendor images on the central disk and still reads legacy rows from the local disk', function (): void {
    Notification::fake();
    $this->tenant = $this->createTenant('legacy', ['owner_id' => $this->owner->id, 'owner_name' => $this->owner->name, 'owner_email' => $this->owner->email]);
    $request = $this->tenant->run(fn (): EditRequest => app(EditRequestService::class)->submit(new EditRequestSubmissionData(
        tenantId: $this->tenant->id,
        productId: $this->product->id,
        variantId: null,
        values: ['name' => ['ar' => 'سماعات ميغز']],
        images: [UploadedFile::fake()->image('vendor.jpg')],
        vendorNote: 'Vendor photo',
    )));

    expect($request->files[0]['disk'])->toBe('central');
    Storage::disk('central')->assertExists($request->files[0]['path']);

    Storage::disk('local')->put('edit-requests/legacy/old.jpg', 'old-bytes');
    $request->forceFill(['files' => [['path' => 'edit-requests/legacy/old.jpg', 'name' => 'old.jpg', 'size' => 9, 'mime_type' => 'image/jpeg'], ...$request->files]])->save();

    $this->actingAs(catalogAdmin(['requests.edits.view', 'requests.edits.manage']), 'admin');
    $this->get($this->centralUrl("/admin/requests/edits/{$request->id}/files/0"))->assertOk();
    $this->get($this->centralUrl("/admin/requests/edits/{$request->id}/files/1"))->assertOk();

    expect(EditRequestService::diskOf('edit-requests/legacy/old.jpg'))->toBe('local')
        ->and(EditRequestService::diskOf($request->files[1]['path'], 'central'))->toBe('central')
        ->and(EditRequestService::diskOf('edit-requests/missing.jpg'))->toBeNull();
});
