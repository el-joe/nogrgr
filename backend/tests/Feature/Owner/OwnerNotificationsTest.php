<?php

use App\Modules\Identity\Enums\AccountStatus;
use App\Modules\Identity\Models\Owner;
use App\Modules\Notifications\Models\PanelNotificationRecord;
use Illuminate\Support\Str;

/*
| OWN-008 Owner portal › Notifications: All / Unread list (own rows only), open, mark
| read / unread, mark all as read, bell feed, sidebar link and guards.
*/

function ownerInboxRow(Owner $owner, array $overrides = [], string $title = 'Store suspended'): string
{
    $id = (string) Str::uuid();

    PanelNotificationRecord::query()->insert(array_replace([
        'id' => $id,
        'type' => 'requests.status_changed',
        'notifiable_type' => $owner->getMorphClass(),
        'notifiable_id' => $owner->id,
        'data' => json_encode(['title' => $title, 'body' => 'Invoice is unpaid', 'url' => 'https://example.test/target']),
        'read_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return $id;
}

beforeEach(function (): void {
    $this->owner = Owner::factory()->create(['locale' => 'en']);
});

dataset('owner notification endpoints', [
    'index' => ['get', 'owner.notifications.index', false],
    'data' => ['get', 'owner.notifications.data', false],
    'latest' => ['get', 'owner.notifications.latest', false],
    'read-all' => ['post', 'owner.notifications.read-all', false],
    'open' => ['get', 'owner.notifications.open', true],
    'mark' => ['patch', 'owner.notifications.mark', true],
]);

it('redirects guests to the login', function (string $method, string $name, bool $withId): void {
    $this->{$method}(route($name, $withId ? [(string) Str::uuid()] : []))->assertRedirect();
})->with('owner notification endpoints');

it('forbids suspended owners', function (string $method, string $name, bool $withId): void {
    $owner = Owner::factory()->create(['status' => AccountStatus::Suspended]);

    $this->actingAs($owner, 'owner')->{$method.'Json'}(route($name, $withId ? [(string) Str::uuid()] : []))->assertForbidden();
})->with('owner notification endpoints');

it('renders the page with tabs, counts and the sidebar link', function (): void {
    ownerInboxRow($this->owner);
    ownerInboxRow($this->owner, ['read_at' => now()]);

    $this->actingAs($this->owner, 'owner')->get(route('owner.notifications.index'))
        ->assertOk()
        ->assertSee(__('notifications::panel.owner.mark_all'))
        ->assertSee(__('notifications::panel.owner.unread'))
        ->assertSee('data-page="owner-notifications"', false)
        ->assertSee(route('owner.notifications.index'), false)
        ->assertDontSee('notifications::panel', false);
});

it('lists only the owner\'s rows and filters unread', function (): void {
    ownerInboxRow($this->owner, [], 'Mine unread');
    ownerInboxRow($this->owner, ['read_at' => now()], 'Mine read');
    ownerInboxRow(Owner::factory()->create(), [], 'Not mine');

    $json = $this->actingAs($this->owner, 'owner')->getJson(route('owner.notifications.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->json();
    expect($json['recordsFiltered'])->toBe(2)
        ->and(collect($json['data'])->pluck('notification')->implode(''))->not->toContain('Not mine');

    $unread = $this->actingAs($this->owner, 'owner')->getJson(route('owner.notifications.data', ['draw' => 1, 'start' => 0, 'length' => 10, 'state' => 'unread']))->json();
    expect($unread['recordsFiltered'])->toBe(1)->and($unread['data'][0]['notification'])->toContain('Mine unread');
});

it('validates and marks rows read / unread, never another owner\'s', function (): void {
    $id = ownerInboxRow($this->owner);
    $foreign = ownerInboxRow(Owner::factory()->create());

    $this->actingAs($this->owner, 'owner')->patchJson(route('owner.notifications.mark', $id), [])->assertUnprocessable()->assertJsonValidationErrors('read');
    $this->actingAs($this->owner, 'owner')->patchJson(route('owner.notifications.mark', $id), ['read' => 1])->assertOk()->assertJsonPath('data.unread', 0);
    $this->actingAs($this->owner, 'owner')->patchJson(route('owner.notifications.mark', $id), ['read' => 0])->assertOk()->assertJsonPath('data.unread', 1);
    $this->actingAs($this->owner, 'owner')->patchJson(route('owner.notifications.mark', $foreign), ['read' => 1])->assertNotFound();
    $this->actingAs($this->owner, 'owner')->get(route('owner.notifications.open', $foreign))->assertNotFound();
});

it('opens a row at its target and marks it read', function (): void {
    $id = ownerInboxRow($this->owner);

    $this->actingAs($this->owner, 'owner')->get(route('owner.notifications.open', $id))->assertRedirect('https://example.test/target');

    expect(PanelNotificationRecord::query()->find($id)?->read_at)->not->toBeNull();
});

it('marks all read and feeds the bell', function (): void {
    ownerInboxRow($this->owner, [], 'Bell row');
    ownerInboxRow($this->owner);

    $this->actingAs($this->owner, 'owner')->getJson(route('owner.notifications.latest'))
        ->assertOk()->assertJsonPath('data.unread', 2)->assertJsonPath('data.items.0.url', fn ($url) => str_contains((string) $url, '/my-account/notifications/'));

    $this->actingAs($this->owner, 'owner')->postJson(route('owner.notifications.read-all'))->assertOk()->assertJsonPath('data.unread', 0);

    expect(PanelNotificationRecord::query()->where('notifiable_id', $this->owner->id)->where('notifiable_type', $this->owner->getMorphClass())->whereNull('read_at')->count())->toBe(0);
});
