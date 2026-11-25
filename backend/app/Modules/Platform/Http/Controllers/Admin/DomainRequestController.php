<?php

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Modules\Platform\DataTables\DomainRequestsDataTable;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Http\Requests\Admin\DomainStoreRequest;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\DnsRecordTemplateService;
use App\Modules\Platform\Services\DomainService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin › Vendors › Domains (ADM-045 / 045a): tabs Domain requests and DNS record
 * templates, request detail (instructions + last check), add a domain to a store,
 * queue a DNS / SSL check and connect / remove / reopen.
 * Permissions: vendors.domains.view / vendors.domains.manage.
 */
class DomainRequestController extends Controller
{
    public const TABS = ['requests', 'dns'];

    public function __construct(
        private readonly DomainService $domains,
        private readonly DnsRecordTemplateService $templates,
    ) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? (string) $request->query('tab') : 'requests';

        return view('platform::admin.domains.index', [
            'tab' => $tab,
            'counts' => $this->domains->counts(),
            'statuses' => DomainRequestStatus::options(),
            'templateCount' => $this->templates->ordered()->count(),
            'preview' => $tab === 'dns' ? $this->templates->preview() : [],
        ]);
    }

    public function data(Request $request, DomainRequestsDataTable $table): JsonResponse
    {
        return $table->toJsonFromRequest($request, DomainRequestsDataTable::FILTERS);
    }

    public function show(DomainRequest $domainRequest): View
    {
        $domainRequest->loadMissing('tenant');

        return view('platform::admin.domains.show', [
            'request' => $domainRequest,
            'instructions' => $this->domains->instructions($domainRequest),
        ]);
    }

    public function store(DomainStoreRequest $request, Tenant $tenant): JsonResponse
    {
        $domainRequest = $this->domains->request($tenant, $request->host());

        return JsonResponder::created(__('platform::domains.actions.requested', ['host' => $domainRequest->host, 'store' => $tenant->name]), ['id' => $domainRequest->id]);
    }

    public function check(DomainRequest $domainRequest): JsonResponse
    {
        $this->domains->queueCheck($domainRequest);

        return JsonResponder::success(__('platform::domains.actions.check_started', ['host' => $domainRequest->host]));
    }

    public function connect(DomainRequest $domainRequest): JsonResponse
    {
        $this->domains->markConnected($domainRequest);

        return JsonResponder::success(__('platform::domains.actions.connected', ['host' => $domainRequest->host]));
    }

    public function remove(DomainRequest $domainRequest): JsonResponse
    {
        $this->domains->remove($domainRequest);

        return JsonResponder::success(__('platform::domains.actions.removed', ['host' => $domainRequest->host]));
    }

    public function reopen(DomainRequest $domainRequest): JsonResponse
    {
        $this->domains->reopen($domainRequest);

        return JsonResponder::success(__('platform::domains.actions.reopened', ['host' => $domainRequest->host]));
    }
}
