<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\DomainType;
use App\Modules\Platform\Models\Domain;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\DomainService;
use App\Modules\Platform\Support\TenantSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Yajra\DataTables\CollectionDataTable;

/**
 * Vendor › Settings › Domains (VEN-082): the store's sub-domain plus its custom-domain
 * requests (removed ones hidden), server-side over the merged rows; search on the host.
 */
final class VendorDomainsDataTable
{
    public function __construct(private readonly DomainService $domains) {}

    public function toJson(Tenant $tenant, ?string $search, bool $canManage): JsonResponse
    {
        $rows = $this->rows($tenant);
        $needle = $search !== null && trim($search) !== '' ? mb_strtolower(trim($search)) : null;

        if ($needle !== null) {
            $rows = $rows->filter(fn (array $row): bool => str_contains($row['host'], $needle))->values();
        }

        return CollectionDataTable::create($rows->all())
            ->filter(static fn (): null => null)
            ->editColumn('host', fn (array $row): string => view('platform::vendor.domains._host-cell', ['row' => $row])->render())
            ->addColumn('status', fn (array $row): string => view('platform::vendor.domains._status-cell', ['row' => $row])->render())
            ->addColumn('actions', fn (array $row): string => view('platform::vendor.domains._actions', ['row' => $row, 'canManage' => $canManage])->render())
            ->rawColumns(['host', 'status', 'actions'])
            ->toJson();
    }

    /**
     * @return Collection<int, array{key: string, host: string, kind: string, domain: Domain|null, request: DomainRequest|null, is_primary: bool, created_at: mixed}>
     */
    public function rows(Tenant $tenant): Collection
    {
        $domains = $this->domains->tenantDomains($tenant);
        $rows = [];

        foreach ($domains as $domain) {
            if ($domain->type === DomainType::Subdomain) {
                $rows[] = $this->row('domain-'.$domain->id, TenantSlug::subdomainHost($domain->domain), 'subdomain', $domain, null, $domain->getAttribute('created_at'));
            }
        }

        foreach ($this->domains->requestsFor($tenant) as $request) {
            if ($request->status === DomainRequestStatus::Removed) {
                continue;
            }

            $domain = $request->domain_id !== null ? $domains->firstWhere('id', $request->domain_id) : null;
            $rows[] = $this->row('request-'.$request->id, $request->host, 'custom', $domain, $request, $request->created_at);
        }

        return collect($rows)
            ->sortByDesc(fn (array $row): int => $row['is_primary'] ? 1 : 0)
            ->values();
    }

    /**
     * @return array{key: string, host: string, kind: string, domain: Domain|null, request: DomainRequest|null, is_primary: bool, created_at: mixed}
     */
    private function row(string $key, string $host, string $kind, ?Domain $domain, ?DomainRequest $request, mixed $createdAt): array
    {
        return ['key' => $key, 'host' => $host, 'kind' => $kind, 'domain' => $domain, 'request' => $request, 'is_primary' => (bool) $domain?->is_primary, 'created_at' => $createdAt];
    }
}
