<?php

use App\Modules\Platform\Enums\DnsRecordType;
use App\Modules\Platform\Models\DnsRecordTemplate;

require_once __DIR__.'/../Vendors/VendorHelpers.php';

/*
| Admin › Vendors › Domains › DNS record templates (ADM-045b): permissions, table JSON,
| preview card and the create / edit / delete modal endpoints.
*/

it('protects the DNS template endpoints', function (): void {
    $template = DnsRecordTemplate::factory()->create();

    $this->get($this->centralUrl('/admin/vendors/domains/dns-records/data'))->assertRedirect($this->centralUrl('/admin/login'));
    $this->post($this->centralUrl('/admin/vendors/domains/dns-records'))->assertRedirect($this->centralUrl('/admin/login'));

    $this->actingAs(vendorsAdmin(['vendors.domains.view']), 'admin');
    $this->getJson($this->centralUrl('/admin/vendors/domains/dns-records/data'))->assertOk();
    $this->postJson($this->centralUrl('/admin/vendors/domains/dns-records'), [])->assertForbidden();
    $this->putJson($this->centralUrl("/admin/vendors/domains/dns-records/{$template->id}"), [])->assertForbidden();
    $this->deleteJson($this->centralUrl("/admin/vendors/domains/dns-records/{$template->id}"))->assertForbidden();

    $this->actingAs(vendorsAdmin(['vendors.view']), 'admin');
    $this->getJson($this->centralUrl('/admin/vendors/domains/dns-records/data'))->assertForbidden();
});

it('renders the DNS tab with the vendor preview and the add button', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    DnsRecordTemplate::factory()->create(['type' => DnsRecordType::CNAME, 'host' => 'www', 'value' => '{subdomain}']);
    DnsRecordTemplate::factory()->create(['type' => DnsRecordType::TXT, 'host' => '_nogrgr', 'value' => 'verify={token}', 'sort_order' => 200]);

    $this->get($this->centralUrl('/admin/vendors/domains?tab=dns'))
        ->assertOk()
        ->assertSee(route('admin.vendors.domains.dns.data'), false)
        ->assertSee(__('platform::domains.dns.preview_title'))
        ->assertSee('yourstore.'.config('tenancy.base_domain'))
        ->assertSee('verify=8f2a5d07c91')
        ->assertSee('data-dns-new', false)
        ->assertSee('dns-record-form', false);

    $this->get($this->centralUrl('/admin/vendors/domains'))->assertOk()->assertDontSee('data-dns-new', false);
});

it('lists templates as JSON in sort order', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');
    $mx = DnsRecordTemplate::factory()->create(['type' => DnsRecordType::MX, 'host' => '@', 'value' => 'mx.example.com', 'priority' => 10, 'sort_order' => 300]);
    $cname = DnsRecordTemplate::factory()->create(['sort_order' => 1]);

    $data = $this->getJson($this->centralUrl('/admin/vendors/domains/dns-records/data?draw=1&start=0&length=25'))->assertOk()->json('data');

    expect(array_column($data, 'id'))->toBe([$cname->id, $mx->id])
        ->and(array_keys($data[0]))->toEqualCanonicalizing(['id', 'type', 'host', 'value', 'ttl', 'priority', 'actions'])
        ->and($data[1]['priority'])->toContain('10')
        ->and($data[1]['actions'])->toContain(route('admin.vendors.domains.dns.update', $mx->id));
});

it('creates, updates and deletes a template', function (): void {
    $this->actingAs(vendorsAdmin(['vendors.domains.view', 'vendors.domains.manage']), 'admin');

    $this->postJson($this->centralUrl('/admin/vendors/domains/dns-records'), [
        'type' => 'CNAME', 'host' => 'www', 'value' => '{subdomain}', 'ttl' => 3600, 'priority' => 5, 'sort_order' => 10,
    ])->assertCreated();

    $template = DnsRecordTemplate::query()->sole();
    expect($template->type)->toBe(DnsRecordType::CNAME)
        ->and($template->priority)->toBeNull();

    $this->putJson($this->centralUrl("/admin/vendors/domains/dns-records/{$template->id}"), [
        'type' => 'MX', 'host' => '@', 'value' => 'mx.nogrgr.com', 'ttl' => 600, 'priority' => 10, 'sort_order' => 20,
    ])->assertOk();

    expect($template->refresh()->type)->toBe(DnsRecordType::MX)
        ->and($template->priority)->toBe(10)
        ->and($template->ttl)->toBe(600);

    $this->deleteJson($this->centralUrl("/admin/vendors/domains/dns-records/{$template->id}"))->assertOk();
    expect(DnsRecordTemplate::query()->count())->toBe(0);
});

it('validates templates', function (): void {
    $this->actingAs(vendorsAdmin(), 'admin');

    $this->postJson($this->centralUrl('/admin/vendors/domains/dns-records'), [
        'type' => 'SRV', 'host' => 'bad host!', 'value' => '', 'ttl' => 30, 'sort_order' => -1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['type', 'host', 'value', 'ttl', 'sort_order']);

    $this->postJson($this->centralUrl('/admin/vendors/domains/dns-records'), [
        'type' => 'MX', 'host' => '@', 'value' => 'mx.nogrgr.com', 'ttl' => 90000, 'sort_order' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors(['ttl', 'priority' => __('platform::domains.validation.priority_required')]);

    expect(DnsRecordTemplate::query()->count())->toBe(0);
});
