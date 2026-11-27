<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\DTOs\MaintenanceWindowData;
use App\Modules\Platform\Enums\MaintenanceScope;
use App\Modules\Platform\Enums\MaintenanceWindowStatus;
use App\Modules\Platform\Exceptions\PlatformRuleException;
use App\Modules\Platform\Models\MaintenanceWindow;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\MaintenanceWindowRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Maintenance windows (ADM-113): create / edit / end now / delete (audited) and the
 * runtime check used by EnforceMaintenanceWindows. The unfinished windows are cached
 * (untagged central store, so the same entry is read inside every store) and busted on
 * every write; the time logic runs per request on the cached snapshot.
 */
class MaintenanceService extends BaseService
{
    public const CACHE_KEY = 'platform:maintenance-windows:v1';

    public function __construct(
        private readonly MaintenanceWindowRepositoryContract $windows,
        private readonly TenantRepositoryContract $tenants,
        private readonly Auditor $auditor,
    ) {}

    public function create(MaintenanceWindowData $data, ?int $adminId): MaintenanceWindow
    {
        /** @var MaintenanceWindow $window */
        $window = $this->windows->create([...$data->attributes(), 'created_by' => $adminId]);
        $this->auditor->record('system.maintenance.created', $window, ['after' => $this->auditValues($window)]);
        $this->forget();

        return $window;
    }

    public function update(MaintenanceWindow $window, MaintenanceWindowData $data): MaintenanceWindow
    {
        $this->ensureOpen($window);
        $before = $this->auditValues($window);

        /** @var MaintenanceWindow $window */
        $window = $this->windows->update($window, $data->attributes());
        $this->auditor->record('system.maintenance.updated', $window, ['before' => $before, 'after' => $this->auditValues($window)]);
        $this->forget();

        return $window;
    }

    /**
     * End a running window now, or cancel a scheduled one.
     */
    public function end(MaintenanceWindow $window, ?int $adminId): MaintenanceWindow
    {
        $this->ensureOpen($window);
        $wasScheduled = $window->status() === MaintenanceWindowStatus::Scheduled;
        $now = now();

        /** @var MaintenanceWindow $window */
        $window = $this->windows->update($window, [
            // a scheduled window that is cancelled ends at its start (status "cancelled")
            'ended_at' => $wasScheduled ? $window->starts_at : $now,
            'ended_by' => $adminId,
        ]);
        $this->auditor->record($wasScheduled ? 'system.maintenance.cancelled' : 'system.maintenance.ended', $window, ['title' => $window->title]);
        $this->forget();

        return $window;
    }

    public function delete(MaintenanceWindow $window): void
    {
        if ($window->status() === MaintenanceWindowStatus::Active) {
            throw new PlatformRuleException(__('platform::maintenance.rules.delete_active'));
        }

        $values = $this->auditValues($window);
        $this->windows->delete($window);
        $this->auditor->record('system.maintenance.deleted', $window, ['before' => $values]);
        $this->forget();
    }

    /**
     * The window that blocks a request right now: platform-wide ones on the central
     * website; platform / storefronts / that store's ones inside a store. Null when
     * none applies or the client IP is allowed by the window.
     *
     * @return array{id: int, message: array<string, string>, ends_at: int|null}|null
     */
    public function blocking(?int $tenantId, ?string $ip, ?Carbon $at = null): ?array
    {
        $now = ($at ?? now())->getTimestamp();

        foreach ($this->snapshot() as $window) {
            $applies = match (MaintenanceScope::from($window['scope'])) {
                MaintenanceScope::Platform => true,
                MaintenanceScope::Storefronts => $tenantId !== null,
                MaintenanceScope::Store => $tenantId !== null && $window['tenant_id'] === $tenantId,
            };

            if (! $applies || $window['starts_at'] > $now || ($window['ends_at'] !== null && $window['ends_at'] <= $now)) {
                continue;
            }

            if ($ip !== null && $window['allowed_ips'] !== [] && IpUtils::checkIp($ip, $window['allowed_ips'])) {
                continue;
            }

            return ['id' => $window['id'], 'message' => $window['message'], 'ends_at' => $window['ends_at']];
        }

        return null;
    }

    /**
     * Next scheduled or running window (page banner).
     */
    public function next(): ?MaintenanceWindow
    {
        return $this->windows->unfinished()->first();
    }

    /**
     * Select2 remote store search (?q=&page=).
     *
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function storeOptions(?string $term, int $page): array
    {
        $perPage = 20;
        $like = '%'.addcslashes((string) $term, '%_\\').'%';
        $query = $this->tenants->query()
            ->when($term !== null && $term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('slug', 'like', $like)))
            ->orderBy('name');
        $total = (clone $query)->count();
        $results = [];

        foreach ($query->forPage(max(1, $page), $perPage)->get(['id', 'name', 'slug']) as $tenant) {
            /** @var Tenant $tenant */
            $results[] = ['id' => (int) $tenant->id, 'text' => $tenant->name.' ('.$tenant->slug.')'];
        }

        return ['results' => $results, 'pagination' => ['more' => $page * $perPage < $total]];
    }

    public function forget(): void
    {
        $this->cache()->forget(self::CACHE_KEY);
    }

    /**
     * @return list<array{id: int, scope: string, tenant_id: int|null, message: array<string, string>, allowed_ips: list<string>, starts_at: int, ends_at: int|null}>
     */
    private function snapshot(): array
    {
        /** @var list<array{id: int, scope: string, tenant_id: int|null, message: array<string, string>, allowed_ips: list<string>, starts_at: int, ends_at: int|null}> $snapshot */
        $snapshot = $this->cache()->rememberForever(self::CACHE_KEY, fn (): array => $this->windows->unfinished()
            ->map(static fn (MaintenanceWindow $window): array => [
                'id' => (int) $window->id,
                'scope' => $window->scope->value,
                'tenant_id' => $window->tenant_id,
                'message' => array_filter((array) $window->message, 'is_string'),
                'allowed_ips' => array_values(array_filter((array) $window->allowed_ips, 'is_string')),
                'starts_at' => $window->starts_at->getTimestamp(),
                'ends_at' => $window->ends_at?->getTimestamp(),
            ])->values()->all());

        return $snapshot;
    }

    /**
     * The default store directly: inside a store the facade is tagged per tenant
     * (CacheTenancyBootstrapper), this entry is platform-wide.
     */
    private function cache(): CacheRepository
    {
        return app('cache')->store();
    }

    private function ensureOpen(MaintenanceWindow $window): void
    {
        if (! $window->status()->isOpen()) {
            throw new PlatformRuleException(__('platform::maintenance.rules.finished'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(MaintenanceWindow $window): array
    {
        return [
            'title' => $window->title,
            'scope' => $window->scope->value,
            'tenant_id' => $window->tenant_id,
            'starts_at' => $window->starts_at->toIso8601String(),
            'ends_at' => $window->ends_at?->toIso8601String(),
            'allowed_ips' => $window->allowed_ips,
        ];
    }
}
