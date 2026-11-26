<?php

namespace App\Modules\Platform\Http\Controllers\Vendor;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Platform\DataTables\VendorDomainsDataTable;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Http\Requests\Vendor\VendorDomainStoreRequest;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\DomainService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Vendor › Settings › Domains (VEN-082 / 082a, Figma Vendor Panel (new screens) 91:116087):
 * the store's sub-domain and custom domains, add a domain (DNS records to create:
 * CNAME / TXT), the selected domain's records with the last check result, "Re-check DNS"
 * (queued CheckDomainDns), verify & connect once every record is found (host added to
 * the tenancy domains), set primary, remove. SSL is issued outside the app: until a
 * certificate is seen the domain shows "SSL pending".
 * Permissions: settings.domains.view (pages) / settings.domains.manage (actions).
 */
class DomainSettingsController extends Controller
{
    public function __construct(
        private readonly DomainService $domains,
        private readonly VendorDomainsDataTable $table,
    ) {}

    public function index(Request $request): View
    {
        $tenant = $this->tenant();
        $selected = $this->domains->requestsFor($tenant)->first(fn (DomainRequest $item): bool => $item->status !== DomainRequestStatus::Removed);

        return $this->page($request, $tenant, $selected);
    }

    public function data(Request $request): JsonResponse
    {
        $search = $request->input('search.value', $request->query('search'));

        return $this->table->toJson($this->tenant(), is_string($search) ? $search : null, $this->canManage($request));
    }

    public function show(Request $request, DomainRequest $domain): View
    {
        return $this->page($request, $this->tenant(), $domain);
    }

    public function store(VendorDomainStoreRequest $request): JsonResponse
    {
        $domain = $this->domains->request($this->tenant(), $request->host());
        $this->domains->queueCheck($domain);

        return JsonResponder::created(
            __('platform::vendor_domains.added', ['host' => $domain->host]),
            ['id' => $domain->id],
            vendor_route('settings.domains.show', ['domain' => $domain->id]),
        );
    }

    public function check(DomainRequest $domain): JsonResponse
    {
        $this->domains->queueCheck($domain);

        return JsonResponder::success(__('platform::vendor_domains.check_started', ['host' => $domain->host]));
    }

    public function connect(DomainRequest $domain): JsonResponse
    {
        $this->domains->connectVerified($domain);

        return JsonResponder::success(__('platform::vendor_domains.connected', ['host' => $domain->host]), redirect: vendor_route('settings.domains.show', ['domain' => $domain->id]));
    }

    public function primary(int $domain): JsonResponse
    {
        $row = $this->domains->setPrimary($this->tenant(), $domain);

        return JsonResponder::success(__('platform::vendor_domains.primary_set'), ['id' => $row->id]);
    }

    public function destroy(DomainRequest $domain): JsonResponse
    {
        $this->domains->remove($domain);

        return JsonResponder::success(__('platform::vendor_domains.removed', ['host' => $domain->host]), redirect: vendor_route('settings.domains.index'));
    }

    private function page(Request $request, Tenant $tenant, ?DomainRequest $selected): View
    {
        return view('platform::vendor.domains.index', [
            'selected' => $selected,
            'instructions' => $selected !== null ? $this->domains->instructions($selected) : [],
            'preview' => $this->domains->instructions(new DomainRequest(['tenant_id' => $tenant->id, 'host' => 'example.com', 'verification_token' => '…'])),
            'canManage' => $this->canManage($request),
        ]);
    }

    private function canManage(Request $request): bool
    {
        $staff = $request->user('vendor');

        return $staff instanceof VendorStaff && $staff->can('settings.domains.manage');
    }

    private function tenant(): Tenant
    {
        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }
}
