<?php

use App\Modules\Commerce\Notifications\OrderAlertNotification;
use App\Modules\Engagement\Notifications\StoreEngagementNotification;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Notifications\DTOs\VendorNotificationPreferencesData;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Enums\VendorNotificationTopic;
use App\Modules\Notifications\Models\VendorNotificationPreference;
use App\Modules\Notifications\Services\VendorNotificationPreferenceService;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;

/*
| VEN-087 Vendor › Settings › Notifications: the member's own topic × channel matrix,
| validation, tenant isolation and enforcement on delivery (NotificationSending).
*/

function vendorPreferencePayload(bool $value = true, array $overrides = []): array
{
    $matrix = [];

    foreach (VendorNotificationTopic::cases() as $topic) {
        foreach (NotificationChannel::cases() as $channel) {
            $matrix[$topic->value][$channel->value] = $overrides[$topic->value][$channel->value] ?? ($value ? '1' : '0');
        }
    }

    return ['preferences' => $matrix];
}

beforeEach(function (): void {
    $this->tenant = $this->createTenant('prefs');
    $this->owner = $this->createVendorOwner($this->tenant);
    $this->url = fn (Tenant $tenant) => $this->tenantUrl($tenant, 'admin/settings/notifications');
});

it('sends guests to the login', function (): void {
    $this->get(($this->url)($this->tenant))->assertRedirect($this->tenantUrl($this->tenant, 'login'));
    $this->putJson(($this->url)($this->tenant), vendorPreferencePayload())->assertUnauthorized();
});

it('forbids members without the notifications permission', function (): void {
    $staff = $this->tenant->run(function (): VendorStaff {
        $staff = VendorStaff::factory()->create();
        $staff->givePermissionTo('dashboard.view');

        return $staff;
    });

    $this->actingAsVendor($staff, $this->tenant)->get(($this->url)($this->tenant))->assertForbidden();
    $this->actingAsVendor($staff, $this->tenant)->putJson(($this->url)($this->tenant), vendorPreferencePayload())->assertForbidden();
});

it('renders the matrix with the defaults and the sidebar link', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->get(($this->url)($this->tenant))
        ->assertOk()
        ->assertSee(__('notifications::panel.preferences.vendor_title'))
        ->assertSee(VendorNotificationTopic::NewOrder->label())
        ->assertSee(VendorNotificationTopic::SettlementDue->label())
        ->assertSee('name="preferences[new_order][email]"', false)
        ->assertSee(($this->url)($this->tenant), false)
        ->assertDontSee('notifications::panel', false);
});

it('validates the full matrix', function (): void {
    $this->actingAsVendor($this->owner, $this->tenant)->putJson(($this->url)($this->tenant), [])
        ->assertUnprocessable()->assertJsonValidationErrors('preferences');

    $payload = vendorPreferencePayload();
    $payload['preferences']['new_order']['email'] = 'maybe';
    unset($payload['preferences']['low_stock']);

    $this->actingAsVendor($this->owner, $this->tenant)->putJson(($this->url)($this->tenant), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['preferences.new_order.email', 'preferences.low_stock']);
});

it('saves the member\'s own switches, audits them and leaves colleagues and other stores alone', function (): void {
    $colleague = $this->createVendorOwner($this->tenant);
    $other = $this->createTenant('prefs-other');
    $otherOwner = $this->createVendorOwner($other);

    $this->actingAsVendor($this->owner, $this->tenant)
        ->putJson(($this->url)($this->tenant), vendorPreferencePayload(true, ['new_order' => ['email' => '0', 'push' => '0']]))
        ->assertOk()
        ->assertJsonPath('data.preferences.new_order.email', false)
        ->assertJsonPath('data.preferences.new_order.in_app', true);

    $service = app(VendorNotificationPreferenceService::class);

    $this->tenant->run(function () use ($service, $colleague): void {
        expect(VendorNotificationPreference::query()->where('vendor_staff_id', $this->owner->id)->count())->toBeGreaterThan(0)
            ->and($service->matrix($this->owner)['new_order']['email'])->toBeFalse()
            ->and($service->matrix($colleague)['new_order']['email'])->toBeTrue()
            ->and(AuditEvent::query()->where('action', 'notification_preferences.updated')->exists())->toBeTrue();
    });

    $other->run(fn () => expect(VendorNotificationPreference::query()->count())->toBe(0)
        ->and($service->matrix($otherOwner)['new_order']['email'])->toBeTrue());
});

it('maps notification types to preference topics', function (string $type, VendorNotificationTopic $topic): void {
    expect(VendorNotificationTopic::fromType($type))->toBe($topic);
})->with([
    ['orders.store_new_order', VendorNotificationTopic::NewOrder],
    ['orders.store_order_cancelled', VendorNotificationTopic::NewOrder],
    ['orders.store_return_opened', VendorNotificationTopic::ReturnRequested],
    ['orders.store_refund_completed', VendorNotificationTopic::ReturnRequested],
    ['payments.payout_sent', VendorNotificationTopic::PayoutSent],
    ['payments.settlement_confirmed', VendorNotificationTopic::SettlementDue],
    ['requests.admin_replied', VendorNotificationTopic::RequestReplies],
    ['system.low_stock', VendorNotificationTopic::LowStock],
    ['system.team_member_joined', VendorNotificationTopic::StoreUpdates],
]);

it('enforces the switches per channel when notifications are sent', function (): void {
    $sent = [];
    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sent): void {
        $sent[] = $event->notification::class.'@'.$event->channel;
    });

    $this->tenant->run(function (): void {
        app(VendorNotificationPreferenceService::class)->update($this->owner, new VendorNotificationPreferencesData(
            vendorPreferencePayload(true, ['new_order' => ['in_app' => false, 'email' => false]])['preferences'],
        ));

        $this->owner->notifyNow(new OrderAlertNotification('store_new_order', ['number' => 'ORD-1', 'total' => '$10.00', 'customer' => 'Sam'], forStore: true));
        $this->owner->notifyNow(new StoreEngagementNotification('review_submitted', ['product' => 'Mug', 'rating' => 5]));
    });

    expect($sent)->not->toContain(OrderAlertNotification::class.'@database')
        ->and($sent)->not->toContain(OrderAlertNotification::class.'@mail')
        ->and($sent)->toContain(StoreEngagementNotification::class.'@database');
});
