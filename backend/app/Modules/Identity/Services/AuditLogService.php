<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\AuditFilterData;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Identity\Support\AuditPresenter;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Repositories\Contracts\AuditEventRepositoryContract;
use App\Modules\Platform\Repositories\Contracts\TenantRepositoryContract;
use App\Modules\Platform\Services\Auditor;
use App\Shared\Services\BaseService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Admin › Audit log (ADM-112 / 112a) over Platform's central `audit_events` (decision
 * #20): filtered query for the table and the CSV export, actor / tenant names, event
 * options and display dates in the viewer's timezone.
 */
class AuditLogService extends BaseService
{
    /**
     * @var array<string, string>
     */
    private array $actorNames = [];

    /**
     * @var array<int, string>
     */
    private array $tenantNames = [];

    public function __construct(
        private readonly AuditEventRepositoryContract $events,
        private readonly AdminRepositoryContract $admins,
        private readonly TenantRepositoryContract $tenants,
        private readonly Auditor $auditor,
    ) {}

    /**
     * @return Builder<AuditEvent>
     */
    public function query(AuditFilterData $filters, ?string $timezone = null): Builder
    {
        $timezone ??= $this->timezone();

        return $this->events->query()
            ->when($filters->actor !== null, fn (Builder $query) => $query
                ->where('actor_type', $filters->actorType ?? (new Admin)->getMorphClass())
                ->where('actor_id', $filters->actor))
            ->when($filters->area !== null, fn (Builder $query) => $query->where('action', 'like', addcslashes((string) $filters->area, '%_\\').'.%'))
            ->when($filters->guards !== null, function (Builder $query) use ($filters): void {
                $guards = array_values(array_filter((array) $filters->guards, static fn (mixed $guard): bool => $guard !== null));
                $withNull = in_array(null, (array) $filters->guards, true);
                $query->where(fn (Builder $query) => $query
                    ->whereIn('guard', $guards === [] ? [''] : $guards)
                    ->when($withNull, fn (Builder $query) => $query->orWhereNull('guard')));
            })
            ->when($filters->tenant !== null, fn (Builder $query) => $query->where('tenant_id', $filters->tenant))
            ->when($filters->event !== null, fn (Builder $query) => $query->where('action', $filters->event))
            ->when($filters->dateFrom !== null, fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse((string) $filters->dateFrom, $timezone)->startOfDay()->utc()))
            ->when($filters->dateTo !== null, fn (Builder $query) => $query->where('created_at', '<=', Carbon::parse((string) $filters->dateTo, $timezone)->endOfDay()->utc()))
            ->when($filters->search !== null, function (Builder $query) use ($filters): void {
                $term = '%'.addcslashes((string) $filters->search, '%_\\').'%';
                $query->where(fn (Builder $query) => $query
                    ->where('action', 'like', $term)
                    ->orWhere('subject_id', 'like', $term)
                    ->orWhere('subject_type', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('url', 'like', $term));
            });
    }

    public function find(int $id): ?AuditEvent
    {
        return $this->events->find($id);
    }

    /**
     * Distinct actions for the "Event" filter, labelled (translated, never the raw action
     * or route name — QA-FINAL-011).
     *
     * @return array<string, string>
     */
    public function eventOptions(): array
    {
        $options = [];

        foreach ($this->events->query()->distinct()->orderBy('action')->limit(500)->pluck('action') as $action) {
            $options[(string) $action] = AuditPresenter::actionLabel((string) $action);
        }

        // Same label for two actions (e.g. a request and its model event): add the area.
        $counts = array_count_values($options);

        foreach ($options as $action => $label) {
            if ($counts[$label] > 1) {
                $options[$action] = $label.' ('.Str::headline(Str::before(Str::after($action, 'http.'), '.')).')';
            }
        }

        asort($options);

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function adminOptions(): array
    {
        return $this->admins->options();
    }

    /**
     * Select2 remote tenant search.
     *
     * @return array{results: list<array{id: int, text: string}>, pagination: array{more: bool}}
     */
    public function tenantOptions(?string $term, int $page): array
    {
        $perPage = 20;
        $query = $this->tenants->query()
            ->when($term !== null && $term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', '%'.addcslashes((string) $term, '%_\\').'%')
                ->orWhere('slug', 'like', '%'.addcslashes((string) $term, '%_\\').'%')))
            ->orderBy('name');
        $total = (clone $query)->count();
        $results = [];

        foreach ($query->forPage(max(1, $page), $perPage)->get(['id', 'name', 'slug']) as $tenant) {
            /** @var Tenant $tenant */
            $results[] = ['id' => (int) $tenant->id, 'text' => $tenant->name.' ('.$tenant->slug.')'];
        }

        return ['results' => $results, 'pagination' => ['more' => $page * $perPage < $total]];
    }

    public function tenantLabel(?int $tenantId): ?string
    {
        if ($tenantId === null) {
            return null;
        }

        if (! array_key_exists($tenantId, $this->tenantNames)) {
            $tenant = $this->tenants->find($tenantId);
            $this->tenantNames[$tenantId] = $tenant instanceof Tenant ? $tenant->name : '#'.$tenantId;
        }

        return $this->tenantNames[$tenantId];
    }

    /**
     * Readable actor: admin / affiliate name from the central DB, otherwise guard + id
     * (tenant users live in their store database).
     */
    public function actorLabel(AuditEvent $event): string
    {
        if ($event->actor_id === null) {
            return (string) __('identity::audit.system_actor');
        }

        $key = $event->actor_type.'#'.$event->actor_id;

        if (! array_key_exists($key, $this->actorNames)) {
            $name = null;

            if ($event->actor_type === (new Admin)->getMorphClass()) {
                $admin = $this->admins->find($event->actor_id);
                $name = $admin instanceof Admin ? $admin->name : null;
            }

            $this->actorNames[$key] = $name ?? AuditPresenter::subjectLabel($event->actor_type).' #'.$event->actor_id;
        }

        return $this->actorNames[$key];
    }

    public function localTime(?Carbon $date, ?string $timezone = null): ?Carbon
    {
        return $date?->copy()->setTimezone($timezone ?? $this->timezone())->locale(app()->getLocale());
    }

    /**
     * CSV export (streamed, chunked) of the filtered events; the export itself is audited.
     */
    public function export(AuditFilterData $filters): Closure
    {
        $this->auditor->record('audit.exported', null, ['filters' => array_filter($filters->toArray(), fn (mixed $value): bool => $value !== null)]);

        return function () use ($filters): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['id', 'created_at', 'guard', 'actor', 'tenant', 'action', 'event', 'subject_type', 'subject_id', 'ip_address', 'url', 'changes'], escape: '\\');

            foreach ($this->query($filters)->lazyByIdDesc(500) as $event) {
                /** @var AuditEvent $event */
                fputcsv($handle, [
                    $event->id,
                    $this->localTime($event->created_at)?->toIso8601String(),
                    $event->guard,
                    $this->actorLabel($event),
                    $this->tenantLabel($event->tenant_id),
                    AuditPresenter::actionLabel($event->action),
                    $event->action,
                    $event->subject_type,
                    $event->subject_id,
                    $event->ip_address,
                    $event->url,
                    $event->changes !== null ? json_encode($event->changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
                ], escape: '\\');
            }

            fclose($handle);
        };
    }

    private function timezone(): string
    {
        $admin = Auth::guard('admin')->user();
        $timezone = $admin instanceof Admin ? ($admin->getAttributes()['timezone'] ?? null) : null;

        return is_string($timezone) && $timezone !== '' ? $timezone : (string) config('app.timezone');
    }
}
