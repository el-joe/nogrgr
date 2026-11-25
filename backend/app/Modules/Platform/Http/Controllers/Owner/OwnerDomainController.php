<?php

namespace App\Modules\Platform\Http\Controllers\Owner;

use App\Modules\Identity\Models\Owner;
use App\Modules\Platform\DataTables\OwnerDomainsDataTable;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Http\Requests\Owner\OwnerDomainStoreRequest;
use App\Modules\Platform\Http\Requests\Owner\OwnerDomainUpdateRequest;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\DomainService;
use App\Modules\Platform\Services\OwnerPortalService;
use App\Shared\Http\Controller;
use App\Shared\Http\JsonResponder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Owner portal › Domains (OWN-004, Figma Affiliate & Owner 122:103025): custom domains
 * of every owned store; add (004a) and edit (004b) modals, remove, and the DNS check
 * page (004c) with pass / fail per record and "Check again" (queued CheckDomainDns).
 */
class OwnerDomainController extends Controller
{
    public function __construct(
        private readonly DomainService $domains,
        private readonly OwnerPortalService $portal,
    ) {}

    public function index(Request $request): View
    {
        $stores = collect($this->portal->cards($this->owner($request)))
            ->mapWithKeys(static fn (array $card): array => [$card['tenant']->id => $card['tenant']->name])
            ->all();

        return view('platform::owner.domains.index', [
            'stores' => $stores,
            'statuses' => DomainRequestStatus::options(),
        ]);
    }

    public function data(Request $request, OwnerDomainsDataTable $table): JsonResponse
    {
        return $table->forOwner($this->owner($request)->id)->toJsonFromRequest($request, OwnerDomainsDataTable::FILTERS);
    }

    /**
     * DNS records the store must add, for the add modal preview (JSON).
     */
    public function instructions(Request $request, Tenant $tenant): JsonResponse
    {
        abort_unless($this->owner($request)->can('owner.stores.manage', $tenant), 403);
        $host = strtolower(trim((string) $request->query('host', '')));
        $preview = new DomainRequest(['tenant_id' => $tenant->id, 'host' => $host !== '' ? $host : 'example.com', 'verification_token' => '…']);

        return JsonResponder::success('', ['records' => $this->domains->instructions($preview)]);
    }

    public function store(OwnerDomainStoreRequest $request): JsonResponse
    {
        $data = $request->toData();
        $tenant = $this->portal->ownedStore($this->owner($request), $data->tenantId);
        abort_if($tenant === null, 403);

        try {
            $domain = $this->domains->request($tenant, $data->host);
        } catch (PlatformRuleException $exception) {
            return JsonResponder::validation(['host' => [$exception->getMessage()]]);
        }

        $this->domains->queueCheck($domain);

        return JsonResponder::created(
            __('platform::owner.domains.added', ['host' => $domain->host, 'store' => $tenant->name]),
            ['id' => $domain->id],
            route('owner.domains.show', $domain->id),
        );
    }

    public function show(Request $request, DomainRequest $domain): View
    {
        $this->authorizeDomain($request, $domain);
        $domain->loadMissing('tenant');

        return view('platform::owner.domains.show', [
            'request' => $domain,
            'instructions' => $this->domains->instructions($domain),
        ]);
    }

    /**
     * Edit modal body (remote modal fragment).
     */
    public function edit(Request $request, DomainRequest $domain): View
    {
        $this->authorizeDomain($request, $domain);

        return view('platform::owner.domains._edit-form', ['request' => $domain->loadMissing('tenant')]);
    }

    public function update(OwnerDomainUpdateRequest $request, DomainRequest $domain): JsonResponse
    {
        $this->authorizeDomain($request, $domain);

        try {
            $this->domains->changeHost($domain, $request->host());
        } catch (PlatformRuleException $exception) {
            return JsonResponder::validation(['host' => [$exception->getMessage()]]);
        }

        $this->domains->queueCheck($domain);

        return JsonResponder::success(__('platform::owner.domains.updated', ['host' => $domain->host]));
    }

    public function check(Request $request, DomainRequest $domain): JsonResponse
    {
        $this->authorizeDomain($request, $domain);
        $this->domains->queueCheck($domain);

        return JsonResponder::success(__('platform::owner.domains.check_started', ['host' => $domain->host]));
    }

    public function destroy(Request $request, DomainRequest $domain): JsonResponse
    {
        $this->authorizeDomain($request, $domain);

        try {
            $this->domains->remove($domain);
        } catch (PlatformRuleException $exception) {
            return JsonResponder::error($exception->getMessage(), 422);
        }

        return JsonResponder::success(__('platform::owner.domains.removed', ['host' => $domain->host]), redirect: $request->boolean('from_detail') ? route('owner.domains.index') : null);
    }

    private function authorizeDomain(Request $request, DomainRequest $domain): void
    {
        abort_unless($this->owner($request)->can('owner.domains.manage', $domain), 403);
    }

    private function owner(Request $request): Owner
    {
        /** @var Owner $owner */
        $owner = $request->user('owner');

        return $owner;
    }
}
