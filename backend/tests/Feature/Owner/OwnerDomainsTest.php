<?php

use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\Enums\DnsCheckStatus;
use App\Modules\Platform\Enums\DnsRecordType;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Jobs\CheckDomainDns;
use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Modules\Platform\Models\DomainRequest;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/OwnerHelpers.php';

/*
| OWN-004 Domains list, 004a add modal (regex validation), 004b edit modal, remove,
| 004c DNS check result (pass / fail per record) — only for the owner's own stores.
*/

beforeEach(function (): void {
    Queue::fake();
    $this->owner = Owner::factory()->create();
    $this->store = ownerStore($this->owner, ['name' => 'Oud House']);
    $this->foreign = ownerStore(Owner::factory()->create(), ['name' => 'Foreign Store']);
    $this->actingAs($this->owner, 'owner');
});

it('renders the domains page with the add modal for the owner\'s stores', function (): void {
    $this->get(route('owner.domains.index'))
        ->assertOk()
        ->assertSee(__('platform::owner.domains.add_title'))
        ->assertSee(route('owner.domains.data'), false)
        ->assertSee('Oud House')
        ->assertDontSee('Foreign Store');
});

it('lists only the owner\'s domains and hides removed ones by default', function (): void {
    $mine = DomainRequest::factory()->create(['tenant_id' => $this->store->id, 'host' => 'www.oudhouse.sa']);
    DomainRequest::factory()->create(['tenant_id' => $this->store->id, 'host' => 'old.oudhouse.sa', 'status' => DomainRequestStatus::Removed]);
    DomainRequest::factory()->create(['tenant_id' => $this->foreign->id, 'host' => 'foreign.example.com']);

    $hosts = collect(ownerRows('owner.domains.data'))->pluck('id')->all();
    expect($hosts)->toBe([$mine->id]);

    $removed = collect(ownerRows('owner.domains.data', ['status' => 'removed']))->pluck('id')->all();
    expect($removed)->toHaveCount(1);

    // A forged owner / tenant filter never widens the list.
    expect(ownerRows('owner.domains.data', ['tenant_id' => $this->foreign->id]))->toBe([]);
    expect(collect(ownerRows('owner.domains.data', ['owner_id' => $this->foreign->owner_id]))->pluck('id')->all())->toBe([$mine->id]);
});

it('previews the DNS records for an owned store only', function (): void {
    DnsRecordTemplate::factory()->create(['type' => DnsRecordType::CNAME, 'host' => 'www', 'value' => '{subdomain}']);

    $this->getJson(route('owner.domains.instructions', ['tenant' => $this->store->id, 'host' => 'oudhouse.sa']))
        ->assertOk()
        ->assertJsonPath('data.records.0.type', 'CNAME')
        ->assertJsonPath('data.records.0.value', $this->store->subdomainHost());

    $this->getJson(route('owner.domains.instructions', ['tenant' => $this->foreign->id]))->assertForbidden();
});

it('validates the added domain', function (array $payload, string $field): void {
    DomainRequest::factory()->create(['tenant_id' => $this->foreign->id, 'host' => 'taken.example.com']);

    $this->postJson(route('owner.domains.store'), array_merge(['tenant_id' => $this->store->id, 'host' => 'shop.example.com'], $payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing host' => [['host' => ''], 'host'],
    'not a domain' => [['host' => 'not a domain'], 'host'],
    'no tld' => [['host' => 'localhost'], 'host'],
    'path' => [['host' => 'shop.example.com/path'], 'host'],
    'already used' => [['host' => 'taken.example.com'], 'host'],
    'missing store' => [['tenant_id' => null], 'tenant_id'],
]);

it('refuses an address on the platform base domain', function (): void {
    $this->postJson(route('owner.domains.store'), ['tenant_id' => $this->store->id, 'host' => 'megz.'.config('tenancy.base_domain')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('host');
});

it('refuses to add a domain to another owner\'s store', function (): void {
    $this->postJson(route('owner.domains.store'), ['tenant_id' => $this->foreign->id, 'host' => 'shop.example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('tenant_id');

    expect(DomainRequest::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('adds a domain (scheme and slashes stripped), queues the DNS check and opens its page', function (): void {
    $response = $this->postJson(route('owner.domains.store'), ['tenant_id' => $this->store->id, 'host' => 'https://WWW.OudHouse.sa/'])->assertCreated();

    $domain = DomainRequest::query()->withoutGlobalScopes()->sole();
    expect($domain->host)->toBe('www.oudhouse.sa')
        ->and($domain->tenant_id)->toBe($this->store->id)
        ->and($response->json('redirect'))->toBe(route('owner.domains.show', $domain->id));

    Queue::assertPushed(CheckDomainDns::class, fn (CheckDomainDns $job): bool => $job->requestId === $domain->id);
});

it('shows the DNS check result with pass / fail per record', function (): void {
    $domain = DomainRequest::factory()->create([
        'tenant_id' => $this->store->id,
        'host' => 'techhub.store',
        'dns_status' => DnsCheckStatus::Missing,
        'last_checked_at' => now()->subMinutes(2),
        'dns_result' => [
            ['type' => 'CNAME', 'host' => 'www.techhub.store', 'expected' => 'stores.example.com', 'found' => ['stores.example.com'], 'ok' => true],
            ['type' => 'A', 'host' => 'techhub.store', 'expected' => '76.76.21.21', 'found' => ['185.12.4.9'], 'ok' => false],
        ],
    ]);

    $response = $this->get(route('owner.domains.show', $domain->id))
        ->assertOk()
        ->assertSee('techhub.store')
        ->assertSee('185.12.4.9')
        ->assertSee(__('platform::owner.domains.dns.pass'))
        ->assertSee(__('platform::owner.domains.dns.fail'))
        ->assertSee(trans_choice('platform::owner.domains.wrong_records', 1, ['count' => 1, 'total' => 2]));

    expect(substr_count($response->getContent(), 'data-dns-row="pass"'))->toBe(1)
        ->and(substr_count($response->getContent(), 'data-dns-row="fail"'))->toBe(1);
});

it('hides another owner\'s domain everywhere', function (): void {
    $domain = DomainRequest::factory()->create(['tenant_id' => $this->foreign->id, 'host' => 'foreign.example.com']);

    $this->get(route('owner.domains.show', $domain->id))->assertForbidden();
    $this->get(route('owner.domains.edit', $domain->id))->assertForbidden();
    $this->putJson(route('owner.domains.update', $domain->id), ['host' => 'mine.example.com'])->assertForbidden();
    $this->postJson(route('owner.domains.check', $domain->id))->assertForbidden();
    $this->deleteJson(route('owner.domains.destroy', $domain->id))->assertForbidden();

    expect($domain->refresh()->host)->toBe('foreign.example.com')
        ->and($domain->status)->toBe(DomainRequestStatus::Pending);
});

it('edits a pending domain and re-checks it', function (): void {
    $domain = DomainRequest::factory()->create(['tenant_id' => $this->store->id, 'host' => 'wrong.example.com', 'dns_status' => DnsCheckStatus::Missing, 'dns_result' => []]);

    $this->get(route('owner.domains.edit', $domain->id))->assertOk()->assertSee('wrong.example.com')->assertSee('domain-edit-form', false);

    $this->putJson(route('owner.domains.update', $domain->id), ['host' => 'bad host'])->assertUnprocessable()->assertJsonValidationErrors('host');

    $this->putJson(route('owner.domains.update', $domain->id), ['host' => 'right.example.com'])->assertOk();

    expect($domain->refresh()->host)->toBe('right.example.com')
        ->and($domain->dns_status)->toBe(DnsCheckStatus::Unchecked);
    Queue::assertPushed(CheckDomainDns::class);
});

it('does not edit a connected domain', function (): void {
    $domain = DomainRequest::factory()->create(['tenant_id' => $this->store->id, 'host' => 'live.example.com', 'status' => DomainRequestStatus::Connected]);

    $this->putJson(route('owner.domains.update', $domain->id), ['host' => 'other.example.com'])->assertUnprocessable();
    expect($domain->refresh()->host)->toBe('live.example.com');
});

it('queues a DNS check again', function (): void {
    $domain = DomainRequest::factory()->create(['tenant_id' => $this->store->id]);

    $this->postJson(route('owner.domains.check', $domain->id))->assertOk()->assertJson(['success' => true]);
    Queue::assertPushed(CheckDomainDns::class, fn (CheckDomainDns $job): bool => $job->requestId === $domain->id);
});

it('removes a domain', function (): void {
    $domain = DomainRequest::factory()->create(['tenant_id' => $this->store->id]);

    $this->deleteJson(route('owner.domains.destroy', $domain->id))->assertOk();
    expect($domain->refresh()->status)->toBe(DomainRequestStatus::Removed);

    $this->deleteJson(route('owner.domains.destroy', $domain->id))->assertUnprocessable();
});
