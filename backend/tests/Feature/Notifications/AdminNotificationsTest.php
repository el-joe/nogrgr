<?php

use App\Modules\Identity\Database\Seeders\CentralPermissionSeeder;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Notifications\AdminRoleChangedNotification;
use App\Modules\Notifications\DTOs\NotificationPreferencesData;
use App\Modules\Notifications\Enums\NotificationCategory;
use App\Modules\Notifications\Enums\NotificationChannel;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Services\NotificationPreferenceService;
use App\Modules\Platform\Models\AuditEvent;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
| Admin › Notifications (ADM-007 / 007a): inbox list + category filter, open, mark
| read / unread / all, delete, bell feed, preferences and channel selection.
*/

function inboxAdmin(?string $permission = 'notifications.view'): Admin
{
    test()->seed(CentralPermissionSeeder::class);
    $admin = Admin::factory()->create(['locale' => 'en']);
    $admin->givePermissionTo(array_values(array_filter(['dashboard.view', $permission])));

    return $admin;
}

function inboxRow(Admin $admin, array $overrides = []): string
{
    $id = (string) Str::uuid();

    $admin->notifications()->getRelated()->newQuery()->insert(array_replace([
        'id' => $id,
        'type' => 'system.role_changed',
        'notifiable_type' => $admin->getMorphClass(),
        'notifiable_id' => $admin->id,
        'data' => json_encode([
            'category' => 'system',
            'event' => 'role_changed',
            'title_key' => 'identity::notifications.role_changed.title',
            'title_params' => ['role' => 'Finance'],
            'body_key' => 'identity::notifications.role_changed.body',
            'body_params' => ['role' => 'Finance'],
            'url' => 'https://example.test/admin/account',
        ]),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

dataset('notification endpoints', [
    'index' => ['get', '/admin/notifications'],
    'data' => ['get', '/admin/notifications/data'],
    'latest' => ['get', '/admin/notifications/latest'],
    'preferences' => ['get', '/admin/notifications/preferences'],
    'preferences.update' => ['put', '/admin/notifications/preferences'],
    'read-all' => ['post', '/admin/notifications/read-all'],
    'open' => ['get', '/admin/notifications/{id}/open'],
    'mark' => ['patch', '/admin/notifications/{id}/read'],
    'destroy' => ['delete', '/admin/notifications/{id}'],
    'broadcast-auth' => ['post', '/admin/notifications/broadcasting/auth'],
]);

it('redirects guests to the admin login', function (string $method, string $path): void {
    $this->{$method}($this->centralUrl(str_replace('{id}', (string) Str::uuid(), $path)))
        ->assertRedirect($this->centralUrl('/admin/login'));
})->with('notification endpoints');

it('forbids admins without the notifications permission', function (string $method, string $path): void {
    $this->actingAs(inboxAdmin(null), 'admin')
        ->{$method.'Json'}($this->centralUrl(str_replace('{id}', (string) Str::uuid(), $path)))
        ->assertForbidden();
})->with('notification endpoints');

it('renders the inbox with category chips, preferences and the live bell', function (): void {
    $admin = inboxAdmin();
    inboxRow($admin);

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/notifications'))
        ->assertOk()
        ->assertSee('data-category-chip="orders"', false)
        ->assertSee('data-notification-bell', false)
        ->assertSee(route('admin.notifications.read-all'), false)
        ->assertSee('Your role is now Finance')
        ->assertSee(__('notifications::panel.preferences.save'));
});

it('lists only my notifications, filtered by category and state', function (): void {
    $admin = inboxAdmin();
    inboxRow($admin);
    inboxRow($admin, ['type' => 'orders.new_order', 'read_at' => now()]);
    inboxRow(Admin::factory()->create());

    $base = '/admin/notifications/data?draw=1&start=0&length=10';

    $this->actingAs($admin, 'admin')->getJson($this->centralUrl($base))->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->actingAs($admin, 'admin')->getJson($this->centralUrl($base.'&category=orders'))->assertJsonPath('recordsFiltered', 1);
    $this->actingAs($admin, 'admin')->getJson($this->centralUrl($base.'&state=unread'))
        ->assertJsonPath('recordsFiltered', 1)
        ->assertJsonPath('data.0.notification', fn (string $html) => str_contains($html, 'Your role is now Finance'));
});

it('opens a notification, marking it read and following its link', function (): void {
    $admin = inboxAdmin();
    $id = inboxRow($admin);

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl("/admin/notifications/{$id}/open"))
        ->assertRedirect($this->centralUrl('/admin/account')); // stored absolute panel URL, resolved on the reader's host (QA-FINAL-008)

    expect($admin->notifications()->whereKey($id)->value('read_at'))->not->toBeNull();
});

it('marks read, unread and all read, and keeps the bell count in sync', function (): void {
    $admin = inboxAdmin();
    $first = inboxRow($admin);
    inboxRow($admin);

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl("/admin/notifications/{$first}/read"), ['read' => 1])
        ->assertOk()->assertJsonPath('data.unread', 1);

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl("/admin/notifications/{$first}/read"), ['read' => 0])
        ->assertOk()->assertJsonPath('data.unread', 2);

    $this->actingAs($admin, 'admin')
        ->patchJson($this->centralUrl("/admin/notifications/{$first}/read"), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['read']);

    $this->actingAs($admin, 'admin')
        ->postJson($this->centralUrl('/admin/notifications/read-all'))
        ->assertOk()->assertJsonPath('data.unread', 0);

    $this->actingAs($admin, 'admin')
        ->getJson($this->centralUrl('/admin/notifications/latest'))
        ->assertOk()->assertJsonPath('data.unread', 0)->assertJsonCount(2, 'data.items');
});

it('deletes my notification but never someone else\'s', function (): void {
    $admin = inboxAdmin();
    $mine = inboxRow($admin);
    $theirs = inboxRow(Admin::factory()->create());

    $this->actingAs($admin, 'admin')->deleteJson($this->centralUrl("/admin/notifications/{$theirs}"))->assertNotFound();
    $this->actingAs($admin, 'admin')->patchJson($this->centralUrl("/admin/notifications/{$theirs}/read"), ['read' => 1])->assertNotFound();
    $this->actingAs($admin, 'admin')->deleteJson($this->centralUrl("/admin/notifications/{$mine}"))->assertOk();

    expect($admin->notifications()->count())->toBe(0);
});

it('saves per-channel preferences, validates them and audits the change', function (): void {
    $admin = inboxAdmin();
    $matrix = [];

    foreach (NotificationCategory::cases() as $category) {
        foreach (NotificationChannel::cases() as $channel) {
            $matrix[$category->value][$channel->value] = '1';
        }
    }

    $matrix['system']['email'] = '0';

    $this->actingAs($admin, 'admin')
        ->get($this->centralUrl('/admin/notifications/preferences'))
        ->assertOk()->assertSee('preferences[system][email]', false);

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/notifications/preferences'), ['preferences' => $matrix])
        ->assertOk()
        ->assertJsonPath('data.preferences.system.email', false);

    expect(NotificationPreference::query()->count())->toBe(1)
        ->and(app(NotificationPreferenceService::class)->allows($admin, NotificationCategory::System, NotificationChannel::Email))->toBeFalse()
        ->and(AuditEvent::query()->where('action', 'notification_preferences.updated')->exists())->toBeTrue();

    $this->actingAs($admin, 'admin')
        ->putJson($this->centralUrl('/admin/notifications/preferences'), ['preferences' => ['system' => ['email' => 'maybe']]])
        ->assertUnprocessable();
});

it('delivers through the channels the admin left on', function (): void {
    Mail::fake();
    $admin = inboxAdmin();
    $notification = new AdminRoleChangedNotification('Finance');

    expect($notification->via($admin))->toBe(['database', 'mail', 'broadcast']);

    app(NotificationPreferenceService::class)->update($admin, new NotificationPreferencesData([
        'system' => ['in_app' => true, 'email' => false, 'push' => false],
    ] + collect(NotificationCategory::cases())->mapWithKeys(fn ($category) => [$category->value => ['in_app' => true, 'email' => true, 'push' => true]])->except('system')->all()));

    expect($notification->via($admin))->toBe(['database']);

    $admin->notify($notification);

    $row = $admin->notifications()->sole();

    expect($row->type)->toBe('system.role_changed')
        ->and($row->title())->toBe('Your role is now Finance')
        ->and($row->category())->toBe(NotificationCategory::System);
});

it('registers the admin channel callback for the owner only', function (): void {
    $admin = inboxAdmin();
    $other = Admin::factory()->create();
    $channels = app(Broadcaster::class)->getChannels();
    $callback = $channels['admin.{id}'];

    expect($callback($admin, (string) $admin->id))->toBeTrue()
        ->and($callback($admin, (string) $other->id))->toBeFalse();
});
