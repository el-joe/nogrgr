<?php

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\Enums\DnsCheckStatus;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\DomainType;
use App\Modules\Platform\Jobs\CheckDomainDns;
use App\Modules\Platform\Models\Domain;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Services\DomainService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| VEN-082 / 082a Vendor › Settings › Domains: permissions, add (validation + queued DNS
| check), records panel, verify & connect only after every record is found, primary,
| remove (primary falls back to the sub-domain) and isolation between stores.
*/

beforeEach(function (): void {
    Queue::fake();
    $this->tenant = $this->createTenant('dom-'.Str::lower(Str::random(6)));
    $this->owner = $this->createVendorOwner($this->tenant, ['email' => 'owner-'.Str::lower(Str::random(6)).'@megz.test']);
    $this->url = fn (string $path = '') => $this->tenantUrl($this->tenant, 'admin/settings/domains'.$path);
    $this->as = fn () => $this->actingAsVendor($this->owner, $this->tenant);
    $this->host = 'shop-'.Str::lower(Str::random(6)).'.example.com';
});

it('guards the pages and actions by the settings.domains permissions', function (): void {
    $this->get(($this->url)())->assertRedirect($this->tenantUrl($this->tenant, 'login'));

    [$none, $viewer] = $this->tenant->run(function (): array {
        $none = VendorStaff::factory()->create();
        $none->givePermissionTo('dashboard.view');
        $viewer = VendorStaff::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'settings.domains.view']);

        return [$none, $viewer];
    });

    $this->actingAsVendor($none, $this->tenant);
    $this->get(($this->url)())->assertForbidden();
    $this->getJson(($this->url)('/data'))->assertForbidden();

    $this->actingAsVendor($viewer, $this->tenant);
    $this->get(($this->url)())->assertOk()->assertSee(__('platform::vendor_domains.title'))->assertDontSee('vendor-domain-add-form', false);
    $this->postJson(($this->url)(), ['host' => $this->host])->assertForbidden();
});

it('lists the sub-domain and validates and adds a custom domain with a queued DNS check', function (): void {
    ($this->as)();

    $this->get(($this->url)())->assertOk()->assertSee('vendor-domain-add-form', false)->assertSee(__('platform::vendor_domains.none_selected_title'));
    $data = $this->getJson(($this->url)('/data').'?draw=1&start=0&length=10')->assertOk()->json('data');
    expect($data)->toHaveCount(1)->and($data[0]['host'])->toContain($this->tenant->slug);

    $this->postJson(($this->url)(), ['host' => 'not a domain'])->assertUnprocessable()->assertJsonValidationErrors('host');
    $this->postJson(($this->url)(), ['host' => 'x.'.config('tenancy.base_domain')])->assertUnprocessable()->assertJsonValidationErrors('host');

    $response = $this->postJson(($this->url)(), ['host' => 'https://'.strtoupper($this->host).'/'])->assertCreated();
    $request = $this->tenant->run(fn () => DomainRequest::query()->sole());
    $response->assertJsonPath('redirect', ($this->url)('/'.$request->id));

    expect($request->host)->toBe($this->host)->and($request->status)->toBe(DomainRequestStatus::Pending);
    Queue::assertPushed(CheckDomainDns::class, fn (CheckDomainDns $job): bool => $job->requestId === $request->id);

    $this->get(($this->url)('/'.$request->id))->assertOk()->assertSee($this->host)->assertSee('_nogrgr-verify')
        ->assertSee(__('platform::vendor_domains.dns.waiting'))->assertDontSee(__('platform::vendor_domains.connect'));
    $this->postJson(($this->url)('/'.$request->id.'/check'))->assertOk();
    expect($this->getJson(($this->url)('/data').'?draw=1&start=0&length=10')->json('data'))->toHaveCount(2);
});

it('connects only verified domains, sets the primary and falls back to the sub-domain on removal', function (): void {
    ($this->as)();
    $request = $this->tenant->run(fn () => app(DomainService::class)->request($this->tenant, $this->host));

    $this->postJson(($this->url)('/'.$request->id.'/connect'))->assertUnprocessable();
    $request->update(['dns_status' => DnsCheckStatus::Ok, 'dns_result' => [['type' => 'TXT', 'host' => '_nogrgr-verify.'.$this->host, 'expected' => 'x', 'found' => ['x'], 'ok' => true]]]);

    $this->get(($this->url)('/'.$request->id))->assertOk()->assertSee(__('platform::vendor_domains.connect'))->assertSee(__('platform::vendor_domains.ssl.pending'));
    $this->postJson(($this->url)('/'.$request->id.'/connect'))->assertOk();

    $custom = Domain::query()->where('domain', $this->host)->sole();
    expect($request->fresh()->status)->toBe(DomainRequestStatus::Connected)->and($custom->tenant_id)->toBe($this->tenant->id);

    $this->postJson(($this->url)('/primary/'.$custom->id))->assertOk();
    $sub = Domain::query()->where('tenant_id', $this->tenant->id)->where('type', DomainType::Subdomain)->sole();
    expect($custom->fresh()->is_primary)->toBeTrue()->and($sub->fresh()->is_primary)->toBeFalse();

    $this->deleteJson(($this->url)('/'.$request->id))->assertOk();
    expect(Domain::query()->where('domain', $this->host)->exists())->toBeFalse()
        ->and($sub->fresh()->is_primary)->toBeTrue()
        ->and($request->fresh()->status)->toBe(DomainRequestStatus::Removed);
});

it('keeps other stores\' domains out of reach', function (): void {
    $other = $this->createTenant('dom-'.Str::lower(Str::random(6)));
    $foreign = $other->run(fn () => app(DomainService::class)->request($other, $this->host));
    $otherSub = Domain::query()->where('tenant_id', $other->id)->sole();

    ($this->as)();
    $this->get(($this->url)('/'.$foreign->id))->assertNotFound();
    $this->postJson(($this->url)('/'.$foreign->id.'/check'))->assertNotFound();
    $this->deleteJson(($this->url)('/'.$foreign->id))->assertNotFound();
    $this->postJson(($this->url)('/primary/'.$otherSub->id))->assertUnprocessable();
    expect($foreign->fresh()->status)->toBe(DomainRequestStatus::Pending)->and($otherSub->fresh()->is_primary)->toBeTrue();
});
