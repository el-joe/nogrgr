<?php

use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Enums\RegistrationStatus;
use App\Modules\Platform\Models\VendorRegistration;
use App\Modules\Platform\Notifications\RegistrationLinkNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| Admin › Vendors › Pending registrations (ADM-042): permissions, list + filters,
| detail, resend completion link and delete.
*/

it('protects every pending-registration endpoint', function (): void {
    $registration = VendorRegistration::factory()->create();
    $id = $registration->id;

    $this->get($this->centralUrl('/admin/vendors/pending'))->assertRedirect($this->centralUrl('/admin/login'));
    $this->get($this->centralUrl('/admin/vendors/pending/data'))->assertRedirect($this->centralUrl('/admin/login'));
    $this->get($this->centralUrl("/admin/vendors/pending/{$id}"))->assertRedirect($this->centralUrl('/admin/login'));
    $this->post($this->centralUrl("/admin/vendors/pending/{$id}/resend"))->assertRedirect($this->centralUrl('/admin/login'));
    $this->delete($this->centralUrl("/admin/vendors/pending/{$id}"))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin([]), 'admin');
    $this->getJson($this->centralUrl('/admin/vendors/pending'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/vendors/pending/data'))->assertForbidden();
    $this->getJson($this->centralUrl("/admin/vendors/pending/{$id}"))->assertForbidden();
    $this->postJson($this->centralUrl("/admin/vendors/pending/{$id}/resend"))->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/vendors/pending/{$id}"))->assertForbidden();
});

it('lets view-only admins browse but not resend or delete', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $registration = VendorRegistration::factory()->status(RegistrationStatus::Incomplete)->create();

    $this->get($this->centralUrl('/admin/vendors/pending'))->assertOk()->assertSee(route('admin.vendors.pending.data'), false);
    $this->get($this->centralUrl("/admin/vendors/pending/{$registration->id}"))
        ->assertOk()
        ->assertSee($registration->email)
        ->assertDontSee(route('admin.vendors.pending.resend', $registration->id), false);

    $row = $this->getJson($this->centralUrl('/admin/vendors/pending/data?draw=1&start=0&length=10'))->json('data.0');
    expect($row['actions'])->not->toContain(route('admin.vendors.pending.resend', $registration->id));

    $this->postJson($this->centralUrl("/admin/vendors/pending/{$registration->id}/resend"))->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/vendors/pending/{$registration->id}"))->assertForbidden();
});

it('lists pending registrations with status, plan and search filters and no secrets', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $growth = Plan::factory()->create(['name' => ['en' => 'Growth', 'ar' => 'نمو']]);
    $reem = VendorRegistration::factory()->status(RegistrationStatus::PaymentPending)->create([
        'email' => 'reem@desertrose.sa', 'store_name' => 'Desert Rose', 'slug' => 'desertrose', 'plan_id' => $growth->id,
        'password' => 'Secret123', 'token_hash' => hash('sha256', 'token'), 'meta' => ['payment_reference' => 'pay_1'],
    ]);
    VendorRegistration::factory()->status(RegistrationStatus::Incomplete)->create(['email' => 'hassan@kidsland.ae']);
    VendorRegistration::factory()->status(RegistrationStatus::Completed)->create();

    $url = fn (string $query = '') => $this->centralUrl('/admin/vendors/pending/data?draw=1&start=0&length=10'.$query);

    $all = $this->getJson($url())->assertOk()->json();
    expect($all['recordsTotal'])->toBe(2);

    $row = collect($all['data'])->firstWhere('id', $reem->id);
    expect(array_keys($row))->toEqualCanonicalizing(['id', 'applicant', 'store', 'plan', 'step', 'started', 'status', 'actions'])
        ->and($row['applicant'])->toContain('reem@desertrose.sa')
        ->and($row['store'])->toContain('Desert Rose')->toContain('desertrose')
        ->and($row['plan'])->toContain('Growth')
        ->and($row['step'])->toBe(RegistrationStatus::PaymentPending->step()->label())
        ->and($row['status'])->toContain(RegistrationStatus::PaymentPending->label())
        ->and($row['actions'])->not->toContain(route('admin.vendors.pending.resend', $reem->id));

    $json = json_encode($all);
    expect($json)->not->toContain(hash('sha256', 'token'))
        ->and($json)->not->toContain('payment_reference')
        ->and($json)->not->toContain('$2y$');

    expect($this->getJson($url('&status=incomplete'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url("&plan_id={$growth->id}"))->json('data.0.id'))->toBe($reem->id)
        ->and($this->getJson($url('&search[value]=kidsland'))->json('recordsFiltered'))->toBe(1);
});

it('shows the registration detail', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $registration = VendorRegistration::factory()->status(RegistrationStatus::EmailUnverified)->create([
        'owner_name' => 'Nour Samir', 'link_sent_at' => now()->subHour(), 'link_sent_count' => 2,
    ]);

    $this->get($this->centralUrl("/admin/vendors/pending/{$registration->id}"))
        ->assertOk()
        ->assertSee('Nour Samir')
        ->assertSee($registration->email)
        ->assertSee(RegistrationStatus::EmailUnverified->label())
        ->assertSee(route('admin.vendors.pending.resend', $registration->id), false)
        ->assertSee(route('admin.vendors.pending.destroy', ['registration' => $registration->id, 'redirect' => 1]), false);

    $this->get($this->centralUrl('/admin/vendors/pending/999999'))->assertNotFound();
});

it('resends the completion link when the status allows it', function (): void {
    Notification::fake();
    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.manage']), 'admin');
    $registration = VendorRegistration::factory()->status(RegistrationStatus::Incomplete)->create(['link_sent_count' => 1]);

    $this->postJson($this->centralUrl("/admin/vendors/pending/{$registration->id}/resend"))
        ->assertOk()
        ->assertJson(['success' => true]);

    $registration->refresh();
    expect($registration->link_sent_count)->toBe(2)
        ->and($registration->link_sent_at)->not->toBeNull()
        ->and($registration->token_hash)->not->toBeNull();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        RegistrationLinkNotification::class,
        fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $registration->email,
    );
});

it('refuses to resend a link while payment is pending or failed', function (RegistrationStatus $status): void {
    Notification::fake();
    $this->actingAs(vendorsAdmin(), 'admin');
    $registration = VendorRegistration::factory()->status($status)->create();

    $this->postJson($this->centralUrl("/admin/vendors/pending/{$registration->id}/resend"))
        ->assertUnprocessable()
        ->assertJson(['success' => false, 'message' => __('platform::messages.link_not_allowed')]);

    Notification::assertNothingSent();
})->with([RegistrationStatus::PaymentPending, RegistrationStatus::PaymentFailed]);

it('throttles resending the link', function (): void {
    Notification::fake();
    $this->actingAs(vendorsAdmin(), 'admin');
    $registration = VendorRegistration::factory()->status(RegistrationStatus::EmailUnverified)->create();

    foreach (range(1, 10) as $attempt) {
        $this->postJson($this->centralUrl("/admin/vendors/pending/{$registration->id}/resend"))->assertOk();
    }

    $this->postJson($this->centralUrl("/admin/vendors/pending/{$registration->id}/resend"))->assertTooManyRequests();
});

it('deletes a pending registration but never a completed one', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.view', 'vendors.delete']), 'admin');
    $pending = VendorRegistration::factory()->status(RegistrationStatus::PaymentFailed)->create();
    $completed = VendorRegistration::factory()->status(RegistrationStatus::Completed)->create();

    $this->deleteJson($this->centralUrl("/admin/vendors/pending/{$pending->id}?redirect=1"))
        ->assertOk()
        ->assertJson(['success' => true, 'redirect' => route('admin.vendors.pending')]);
    expect(VendorRegistration::query()->whereKey($pending->id)->exists())->toBeFalse();

    $this->deleteJson($this->centralUrl("/admin/vendors/pending/{$completed->id}"))
        ->assertUnprocessable()
        ->assertJson(['message' => __('platform::messages.registration_closed')]);
    expect(VendorRegistration::query()->whereKey($completed->id)->exists())->toBeTrue();
});
