<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Contracts\CertificateInspector;
use App\Modules\Platform\Contracts\DnsResolver;
use App\Modules\Platform\Enums\DnsCheckStatus;
use App\Modules\Platform\Enums\DnsRecordType;
use App\Modules\Platform\Enums\DomainRequestStatus;
use App\Modules\Platform\Enums\DomainType;
use App\Modules\Platform\Enums\SslStatus;
use App\Modules\Platform\Events\DomainConnected;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Jobs\CheckDomainDns;
use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Modules\Platform\Models\Domain;
use App\Modules\Platform\Models\DomainRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\DnsRecordTemplateRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\DomainRequestRepositoryContract;
use App\Modules\Platform\Support\TenantSlug;
use App\Shared\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Custom domains: request lifecycle (pending → connected → removed → pending), the queued
 * DNS / SSL check (CheckDomainDns → runCheck) and the per-store DNS instructions rendered
 * from the platform templates. Connecting adds the host to the tenancy `domains` table;
 * removing deletes it again so the host stops resolving to the store.
 */
class DomainService extends BaseService
{
    public function __construct(
        private readonly DomainRequestRepositoryContract $requests,
        private readonly DnsRecordTemplateRepositoryContract $templates,
        private readonly DnsResolver $dns,
        private readonly CertificateInspector $certificates,
    ) {}

    /**
     * @return array<string, int> all + per status
     */
    public function counts(): array
    {
        $byStatus = $this->requests->countsByStatus();
        $counts = ['all' => array_sum($byStatus)];

        foreach (DomainRequestStatus::cases() as $status) {
            $counts[$status->value] = $byStatus[$status->value] ?? 0;
        }

        return $counts;
    }

    /**
     * @return Collection<int, DomainRequest>
     */
    public function requestsFor(Tenant $tenant): Collection
    {
        return $this->requests->forTenant((int) $tenant->id);
    }

    public function request(Tenant $tenant, string $host): DomainRequest
    {
        $host = Str::lower(trim($host));

        if ($this->requests->hostInUse($host)) {
            throw PlatformRuleException::domainTaken($host);
        }

        /** @var DomainRequest $request */
        $request = $this->requests->create([
            'tenant_id' => $tenant->id,
            'host' => $host,
            'verification_token' => Str::lower(Str::random(32)),
        ]);

        return $request;
    }

    /**
     * Correct the host of a request that is not connected yet (owner portal edit,
     * OWN-004b): the previous check no longer applies.
     */
    public function changeHost(DomainRequest $request, string $host): DomainRequest
    {
        $host = Str::lower(trim($host));

        if ($request->status !== DomainRequestStatus::Pending) {
            throw PlatformRuleException::invalidTransition($request->status->label(), DomainRequestStatus::Pending->label());
        }

        if ($host === $request->host) {
            return $request;
        }

        if ($this->requests->hostInUse($host, $request->id)) {
            throw PlatformRuleException::domainTaken($host);
        }

        $this->requests->update($request, [
            'host' => $host,
            'dns_status' => DnsCheckStatus::Unchecked,
            'dns_result' => null,
            'ssl_status' => SslStatus::Unknown,
            'ssl_expires_at' => null,
            'last_checked_at' => null,
        ]);

        return $request;
    }

    public function markConnected(DomainRequest $request): DomainRequest
    {
        $this->guard($request, DomainRequestStatus::Connected);

        $this->transaction(function () use ($request): void {
            /** @var Tenant $tenant */
            $tenant = $request->tenant()->withoutGlobalScopes()->firstOrFail();
            /** @var Domain $domain */
            $domain = $tenant->domains()->firstOrCreate(['domain' => $request->host], ['type' => DomainType::Custom, 'is_primary' => false]);

            $this->requests->update($request, [
                'status' => DomainRequestStatus::Connected,
                'domain_id' => $domain->id,
                'connected_at' => now(),
                'removed_at' => null,
            ]);

            $this->dispatchAfterCommit(new DomainConnected($request));
        }, $request->getConnectionName());

        return $request;
    }

    public function remove(DomainRequest $request): DomainRequest
    {
        $this->guard($request, DomainRequestStatus::Removed);

        $this->transaction(function () use ($request): void {
            /** @var Domain|null $domain */
            $domain = $request->domain()->first();

            if ($domain !== null && $domain->is_primary) {
                $this->restoreSubdomainPrimary((int) $request->tenant_id, (int) $domain->id);
            }

            $domain?->delete();

            $this->requests->update($request, [
                'status' => DomainRequestStatus::Removed,
                'domain_id' => null,
                'removed_at' => now(),
            ]);
        }, $request->getConnectionName());

        return $request;
    }

    public function reopen(DomainRequest $request): DomainRequest
    {
        $this->guard($request, DomainRequestStatus::Pending);

        if ($this->requests->hostInUse($request->host, $request->id)) {
            throw PlatformRuleException::domainTaken($request->host);
        }

        $this->requests->update($request, [
            'status' => DomainRequestStatus::Pending,
            'removed_at' => null,
            'dns_status' => DnsCheckStatus::Unchecked,
            'dns_result' => null,
        ]);

        return $request;
    }

    public function queueCheck(DomainRequest $request): void
    {
        CheckDomainDns::dispatch($request->id)->onQueue('default');
    }

    /**
     * CheckDomainDns body: compares the live DNS against the rendered templates and
     * reads the TLS certificate.
     */
    public function runCheck(DomainRequest $request): DomainRequest
    {
        $rows = [];

        foreach ($this->instructions($request) as $record) {
            $type = DnsRecordType::from($record['type']);
            $host = $this->fqdn($record['host'], $request->host);
            $expected = strtolower(rtrim($record['value'], '.'));
            $found = $this->dns->lookup($host, $type);

            $rows[] = [
                'type' => $type->value,
                'host' => $host,
                'expected' => $expected,
                'found' => $found,
                'ok' => in_array($expected, $found, true),
            ];
        }

        $status = match (true) {
            $rows === [] => DnsCheckStatus::Unchecked,
            collect($rows)->every(fn (array $row): bool => $row['ok']) => DnsCheckStatus::Ok,
            default => DnsCheckStatus::Missing,
        };
        $validUntil = $this->certificates->validUntil($request->host);

        $this->requests->update($request, [
            'dns_status' => $status,
            'dns_result' => $rows,
            'ssl_status' => $validUntil !== null ? SslStatus::Valid : ($status === DnsCheckStatus::Ok ? SslStatus::Invalid : SslStatus::Unknown),
            'ssl_expires_at' => $validUntil,
            'last_checked_at' => now(),
        ]);

        return $request;
    }

    /**
     * Records the vendor must add, with {domain} / {subdomain} / {token} filled in.
     *
     * @return list<array{type: string, host: string, value: string, ttl: int, priority: int|null}>
     */
    public function instructions(DomainRequest $request): array
    {
        /** @var Tenant|null $tenant */
        $tenant = $request->tenant()->withoutGlobalScopes()->first();
        $replacements = [
            '{domain}' => $request->host,
            '{subdomain}' => $tenant !== null ? TenantSlug::subdomainHost($tenant->slug) : '',
            '{token}' => $request->verification_token,
        ];

        $templates = $this->templates->ordered();

        if ($templates->isEmpty()) {
            return $this->defaultRecords($replacements);
        }

        return $templates
            ->map(fn (DnsRecordTemplate $template): array => $template->render($replacements))
            ->values()
            ->all();
    }

    /**
     * Platform default record set (tenancy.custom_domain_records) used until the admin
     * defines templates; entries with an unknown type or an empty value are skipped.
     *
     * @param  array<string, string>  $replacements
     * @return list<array{type: string, host: string, value: string, ttl: int, priority: int|null}>
     */
    private function defaultRecords(array $replacements): array
    {
        $records = [];

        foreach ((array) config('tenancy.custom_domain_records', []) as $record) {
            $type = DnsRecordType::tryFrom(strtoupper((string) ($record['type'] ?? '')));
            $value = strtr(trim((string) ($record['value'] ?? '')), $replacements);

            if ($type === null || $value === '') {
                continue;
            }

            $records[] = [
                'type' => $type->value,
                'host' => strtr((string) ($record['host'] ?? '@'), $replacements),
                'value' => $value,
                'ttl' => (int) ($record['ttl'] ?? 3600),
                'priority' => isset($record['priority']) ? (int) $record['priority'] : null,
            ];
        }

        return $records;
    }

    /**
     * "@" → the domain itself; "www" → www.domain; an absolute host stays as is.
     */
    private function fqdn(string $host, string $domain): string
    {
        $host = strtolower(trim($host));

        return match (true) {
            $host === '' || $host === '@' => $domain,
            str_ends_with($host, '.'.$domain) || $host === $domain => $host,
            default => $host.'.'.$domain,
        };
    }

    /**
     * Vendor "Verify & connect" (VEN-082a): only once the last DNS check found every
     * record; admins can still connect by hand (ADM domain requests).
     */
    public function connectVerified(DomainRequest $request): DomainRequest
    {
        if ($request->dns_status !== DnsCheckStatus::Ok) {
            throw new PlatformRuleException(__('platform::vendor_domains.errors.not_verified', ['host' => $request->host]));
        }

        return $this->markConnected($request);
    }

    /**
     * Tenancy domain rows of the store (sub-domain + connected custom hosts).
     *
     * @return Collection<int, Domain>
     */
    public function tenantDomains(Tenant $tenant): Collection
    {
        /** @var Collection<int, Domain> $domains */
        $domains = Domain::query()->where('tenant_id', $tenant->id)->orderBy('id')->get();

        return $domains;
    }

    /**
     * Make one of the store's tenancy domains (its sub-domain or a connected custom
     * host) the primary one; exactly one primary per store.
     */
    public function setPrimary(Tenant $tenant, int $domainId): Domain
    {
        /** @var Domain|null $domain */
        $domain = Domain::query()->where('tenant_id', $tenant->id)->whereKey($domainId)->first();

        if ($domain === null) {
            throw new PlatformRuleException(__('platform::vendor_domains.errors.not_connected'));
        }

        $this->transaction(function () use ($tenant, $domain): void {
            Domain::query()->where('tenant_id', $tenant->id)->whereKeyNot($domain->id)->update(['is_primary' => false]);
            $domain->forceFill(['is_primary' => true])->save();
        }, $domain->getConnectionName());

        return $domain;
    }

    /**
     * The primary custom host is being removed: the sub-domain becomes primary again.
     */
    private function restoreSubdomainPrimary(int $tenantId, int $exceptId): void
    {
        $fallback = Domain::query()->where('tenant_id', $tenantId)->whereKeyNot($exceptId)
            ->orderByRaw('case when type = ? then 0 else 1 end', [DomainType::Subdomain->value])->orderBy('id')->first();
        $fallback?->forceFill(['is_primary' => true])->save();
    }

    private function guard(DomainRequest $request, DomainRequestStatus $next): void
    {
        if (! $request->status->canTransitionTo($next)) {
            throw PlatformRuleException::invalidTransition($request->status->label(), $next->label());
        }
    }
}
