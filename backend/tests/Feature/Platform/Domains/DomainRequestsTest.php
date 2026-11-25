<?php

use App\Modules\Platform\Enums\DnsCheckStatus;
use App\Modules\Platform\Enums\DnsRecordType;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\SslStatus;
use App\Modules\Platform\Jobs\CheckDomainDns;
use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Services\DomainService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| Admin › Vendors › Domains (ADM-045 / 045a): permissions, list + filters, detail,
| add a domain to a store, queued DNS check and connect / remove / reopen.
*/

it('protects every domain-request endpoint', function (): void {
    $tenant = vendorsTenant();
    $request = DomainRequest::factory()->create(['tenant_id' => $tenant->id]);
    $base = "/admin/vendors/domains/{$request->id}";

    foreach (['/admin/vendors/domains', '/admin/vendors/domains/data', $base] as $path) {
        $this->get($this->centralUrl($path))->assertRedirect($this->centralUrl('/admin/login'));
    }
    $this->post($this->centralUrl("/admin/vendors/{$tenant->id}/domains"))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $this->getJson($this->centralUrl('/admin/vendors/domains'))->assertForbidden();
    $this->getJson($this->centralUrl('/admin/vendors/domains/data'))->assertForbidden();
    $this->getJson($this->centralUrl($base))->assertForbidden();

    $this->actingAs(vendorsAdmin(['vendors.domains.view']), 'admin');
    $this->get($this->centralUrl('/admin/vendors/domains'))->assertOk();
    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/domains"), ['host' => 'megz.sa'])->assertForbidden();
    foreach (['check', 'connect', 'remove', 'reopen'] as $action) {
        $this->postJson($this->centralUrl("{$base}/{$action}"))->assertForbidden();
    }
});

it('renders the requests tab and hides manage actions from viewers', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.domains.view']), 'admin');
    $request = DomainRequest::factory()->create(['tenant_id' => vendorsTenant()->id]);

    $this->get($this->centralUrl('/admin/vendors/domains'))
        ->assertOk()
        ->assertSee(route('admin.vendors.domains.data'), false)
        ->assertSee(__('platform::domains.tabs.dns'))
        ->assertDontSee('data-dns-new', false);

    $row = $this->getJson($this->centralUrl('/admin/vendors/domains/data?draw=1&start=0&length=10'))->json('data.0');
    expect($row['actions'])->toContain(route('admin.vendors.domains.show', $request->id))
        ->and($row['actions'])->not->toContain(route('admin.vendors.domains.connect', $request->id));
});

it('lists domain requests with status and search filters and no tokens', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $megz = vendorsTenant(['name' => 'Megz Store', 'slug' => 'megz-'.Str::lower(Str::random(5))]);
    $connected = DomainRequest::factory()->create(['tenant_id' => $megz->id, 'host' => 'megz.sa', 'status' => DomainRequestStatus::Connected, 'dns_status' => DnsCheckStatus::Ok, 'ssl_status' => SslStatus::Valid]);
    $missing = DomainRequest::factory()->create([
        'tenant_id' => vendorsTenant(['name' => 'Desert Rose'])->id, 'host' => 'desertrose.sa', 'dns_status' => DnsCheckStatus::Missing,
        'dns_result' => [['type' => 'CNAME', 'host' => 'www.desertrose.sa', 'expected' => 'x', 'found' => [], 'ok' => false]],
    ]);
    DomainRequest::factory()->create(['tenant_id' => vendorsTenant()->id, 'host' => 'oldstore.com', 'status' => DomainRequestStatus::Removed, 'removed_at' => now()]);

    $url = fn (string $query = '') => $this->centralUrl('/admin/vendors/domains/data?draw=1&start=0&length=10'.$query);

    $all = $this->getJson($url())->assertOk()->json();
    expect($all['recordsTotal'])->toBe(3);

    $row = collect($all['data'])->firstWhere('id', $connected->id);
    expect(array_keys($row))->toEqualCanonicalizing(['id', 'domain', 'store', 'dns', 'ssl', 'status', 'actions'])
        ->and($row['domain'])->toContain('megz.sa')
        ->and($row['store'])->toContain('Megz Store')->toContain($megz->subdomainHost())
        ->and($row['dns'])->toContain(__('platform::domains.dns_check.ok'))
        ->and($row['ssl'])->toContain(SslStatus::Valid->label())
        ->and($row['status'])->toContain(DomainRequestStatus::Connected->label());

    expect(collect($all['data'])->firstWhere('id', $missing->id)['dns'])->toContain('CNAME missing');
    expect(json_encode($all))->not->toContain($connected->verification_token);

    expect($this->getJson($url('&status=removed'))->json('recordsFiltered'))->toBe(1)
        ->and($this->getJson($url('&search[value]=desert'))->json('data.0.id'))->toBe($missing->id);
});

it('shows instructions and the last check on the detail page', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant(['name' => 'Desert Rose']);
    DnsRecordTemplate::factory()->create(['type' => DnsRecordType::TXT, 'host' => '_nogrgr', 'value' => 'verify={token}', 'ttl' => 300]);
    $request = DomainRequest::factory()->create([
        'tenant_id' => $tenant->id, 'host' => 'desertrose.sa', 'dns_status' => DnsCheckStatus::Missing, 'last_checked_at' => now(),
        'dns_result' => [['type' => 'TXT', 'host' => '_nogrgr.desertrose.sa', 'expected' => 'verify=abc', 'found' => ['verify=old'], 'ok' => false]],
    ]);

    $this->get($this->centralUrl("/admin/vendors/domains/{$request->id}"))
        ->assertOk()
        ->assertSee('desertrose.sa')
        ->assertSee('verify='.$request->verification_token)
        ->assertSee('verify=old')
        ->assertSee(route('admin.vendors.domains.check', $request->id), false)
        ->assertSee(route('admin.vendors.domains.connect', $request->id), false)
        ->assertDontSee(route('admin.vendors.domains.reopen', $request->id), false);

    $unchecked = DomainRequest::factory()->create(['tenant_id' => $tenant->id]);
    $this->get($this->centralUrl("/admin/vendors/domains/{$unchecked->id}"))->assertOk()->assertSee(__('platform::domains.check.empty_title'));
});

it('adds a custom domain to a store', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.domains.view', 'vendors.domains.manage']), 'admin');
    $tenant = vendorsTenant();

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/domains"), ['host' => ' https://Shop.Megz.SA/ '])
        ->assertCreated()
        ->assertJson(['success' => true]);

    $request = DomainRequest::query()->where('tenant_id', $tenant->id)->sole();
    expect($request->host)->toBe('shop.megz.sa')
        ->and($request->status)->toBe(DomainRequestStatus::Pending);
});

it('validates the requested host', function (string $host, string $message): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();
    DomainRequest::factory()->create(['tenant_id' => vendorsTenant()->id, 'host' => 'taken.com']);
    DomainRequest::factory()->create(['tenant_id' => vendorsTenant()->id, 'host' => 'free-again.com', 'status' => DomainRequestStatus::Removed]);

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/domains"), ['host' => $host])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['host' => $message === 'platform' ? __('platform::domains.validation.platform_domain', ['base' => config('tenancy.base_domain')]) : $message]);

    $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/domains"), ['host' => 'free-again.com'])->assertCreated();
})->with([
    'not a host' => ['not a domain', 'Enter a domain name such as mystore.com, without https:// or a path.'],
    'taken' => ['taken.com', 'The domain taken.com is already used by another store.'],
]);

it('refuses the platform base domain', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    config(['tenancy.base_domain' => 'nogrgr.test']);
    $tenant = vendorsTenant();

    foreach (['nogrgr.test', 'shop.nogrgr.test'] as $host) {
        $this->postJson($this->centralUrl("/admin/vendors/{$tenant->id}/domains"), ['host' => $host])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['host' => __('platform::domains.validation.platform_domain', ['base' => 'nogrgr.test'])]);
    }
});

it('queues a DNS check', function (): void {
    Queue::fake();
    $this->actingAs(vendorsAdmin(), 'admin');
    $request = DomainRequest::factory()->create(['tenant_id' => vendorsTenant()->id]);

    $this->postJson($this->centralUrl("/admin/vendors/domains/{$request->id}/check"))
        ->assertOk()
        ->assertJson(['message' => __('platform::domains.actions.check_started', ['host' => $request->host])]);

    Queue::assertPushed(CheckDomainDns::class, fn (CheckDomainDns $job): bool => $job->requestId === $request->id);
});

it('dispatches the check through the bus and stores the result', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();
    DnsRecordTemplate::factory()->create(['type' => DnsRecordType::CNAME, 'host' => 'www', 'value' => '{subdomain}']);
    $request = DomainRequest::factory()->create(['tenant_id' => $tenant->id, 'host' => 'megz.sa']);

    Bus::fake();
    $this->postJson($this->centralUrl("/admin/vendors/domains/{$request->id}/check"))->assertOk();
    Bus::assertDispatched(CheckDomainDns::class);

    fakeDns(['www.megz.sa|CNAME' => [$tenant->subdomainHost()]], now()->addMonths(3));
    app(DomainService::class)->runCheck($request);

    $request->refresh();
    expect($request->dns_status)->toBe(DnsCheckStatus::Ok)
        ->and($request->ssl_status)->toBe(SslStatus::Valid)
        ->and($request->last_checked_at)->not->toBeNull();

    fakeDns([]);
    app(DomainService::class)->runCheck($request);
    expect($request->refresh()->dns_status)->toBe(DnsCheckStatus::Missing)
        ->and($request->missingRecordTypes())->toBe(['CNAME'])
        ->and($request->ssl_status)->toBe(SslStatus::Unknown);
});

it('connects, removes and reopens a domain', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $tenant = vendorsTenant();
    $request = DomainRequest::factory()->create(['tenant_id' => $tenant->id, 'host' => 'megz.sa']);
    $base = "/admin/vendors/domains/{$request->id}";

    $this->postJson($this->centralUrl("{$base}/connect"))->assertOk();
    expect($request->refresh()->status)->toBe(DomainRequestStatus::Connected)
        ->and($tenant->domains()->where('domain', 'megz.sa')->exists())->toBeTrue();

    $this->postJson($this->centralUrl("{$base}/reopen"))->assertUnprocessable()->assertJson(['success' => false]);
    $this->postJson($this->centralUrl("{$base}/connect"))->assertUnprocessable();

    $this->postJson($this->centralUrl("{$base}/remove"))->assertOk();
    expect($request->refresh()->status)->toBe(DomainRequestStatus::Removed)
        ->and($tenant->domains()->where('domain', 'megz.sa')->exists())->toBeFalse();

    $this->postJson($this->centralUrl("{$base}/remove"))->assertUnprocessable();

    $this->postJson($this->centralUrl("{$base}/reopen"))->assertOk();
    expect($request->refresh()->status)->toBe(DomainRequestStatus::Pending);
});

it('refuses to reopen a host another store took meanwhile', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $removed = DomainRequest::factory()->create(['tenant_id' => vendorsTenant()->id, 'host' => 'shared.sa', 'status' => DomainRequestStatus::Removed]);
    DomainRequest::factory()->create(['tenant_id' => vendorsTenant()->id, 'host' => 'shared.sa']);

    $this->postJson($this->centralUrl("/admin/vendors/domains/{$removed->id}/reopen"))
        ->assertUnprocessable()
        ->assertJson(['message' => __('platform::messages.domain_taken', ['host' => 'shared.sa'])]);
});
