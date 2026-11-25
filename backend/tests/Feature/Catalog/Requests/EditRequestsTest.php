<?php

use App\Modules\Catalog\DTOs\Requests\EditRequestSubmissionData;
use App\Modules\Catalog\Enums\EditRequestStatus;
use App\Modules\Catalog\Exceptions\RequestRuleException;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Requests\EditRequest;
use App\Modules\Catalog\Models\Requests\ProductOverride;
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
    Storage::disk('local')->assertExists($request->files[0]['path']);

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

it('approves an edit request into a tenant override without touching the central product', function (): void {
    Notification::fake();
    $request = submitEditRequest($this, ['name' => ['ar' => 'سماعات ميغز برو']], [UploadedFile::fake()->image('front.jpg')]);
    $this->actingAs(catalogAdmin(['requests.edits.manage']), 'admin');

    $this->postJson($this->centralUrl("/admin/requests/edits/{$request->id}/approve"))->assertOk();

    $overrides = ProductOverride::withoutTenantScope()->where('edit_request_id', $request->id)->get()->keyBy('field');
    $images = json_decode((string) $overrides['images']->value, true);

    expect($request->refresh()->status)->toBe(EditRequestStatus::Approved)
        ->and($overrides)->toHaveCount(2)
        ->and($overrides['name']->tenant_id)->toBe($this->tenant->id)
        ->and($overrides['name']->locale)->toBe('ar')
        ->and($overrides['name']->value)->toBe('سماعات ميغز برو')
        ->and($overrides['name']->central_value)->toBe('سماعات لاسلكية برو')
        ->and($images)->toHaveCount(1)
        ->and($this->product->refresh()->getTranslation('name', 'ar'))->toBe('سماعات لاسلكية برو');
    Storage::disk('public')->assertExists($images[0]);
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
        ->and($request->reason)->toBe('Brand names are not allowed in titles.')
        ->and(ProductOverride::withoutTenantScope()->count())->toBe(0);
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
