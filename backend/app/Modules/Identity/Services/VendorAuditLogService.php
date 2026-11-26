<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTOs\AuditFilterData;
use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Services\StoreGeneralSettingsService;
use App\Shared\Services\BaseService;
use App\Shared\Support\Ui;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Vendor › Settings › Audit log (VEN-086, Figma Vendor Panel 91:118575): the current
 * store's slice of Platform's central audit trail through the shared AuditLogService
 * (decision #20; AuditEvent is tenant-scoped in store context). Only team actions are
 * listed — store staff, Nogrgr support (admins, incl. impersonation) and the system;
 * shopper activity stays out. Member = store staff, Area = action prefix, IPs masked,
 * times in the store timezone.
 */
class VendorAuditLogService extends BaseService
{
    /**
     * Guards of the events a store sees (null = system).
     */
    public const GUARDS = ['vendor', 'admin', null];

    /**
     * @var array<string, array{name: string, role: string}>
     */
    private array $actors = [];

    private ?string $timezone = null;

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly VendorStaffRepositoryContract $staff,
        private readonly AdminRepositoryContract $admins,
        private readonly StoreGeneralSettingsService $general,
    ) {}

    /**
     * @return Builder<AuditEvent>
     */
    public function query(AuditFilterData $filters): Builder
    {
        return $this->audit->query($filters->scopedTo((new VendorStaff)->getMorphClass(), self::GUARDS), $this->timezone());
    }

    /**
     * "Member" filter: the store team (id => name).
     *
     * @return array<int, string>
     */
    public function memberOptions(): array
    {
        return $this->staff->query()->orderBy('name')->pluck('name', 'id')->map(static fn (mixed $name): string => (string) $name)->all();
    }

    /**
     * "Area" filter: first segment of the actions recorded for this store.
     *
     * @return array<string, string>
     */
    public function areaOptions(): array
    {
        $areas = [];

        foreach ($this->query(new AuditFilterData)->reorder()->distinct()->limit(500)->pluck('action') as $action) {
            $area = Str::before((string) $action, '.');

            if (preg_match('/^[a-z0-9_]+$/', $area) === 1) {
                $areas[$area] = self::areaLabel($area);
            }
        }

        asort($areas);

        return $areas;
    }

    public static function areaLabel(string $area): string
    {
        $key = 'identity::vendor_audit.areas.'.$area;
        $label = __($key);

        return is_string($label) && $label !== $key ? $label : Str::headline($area);
    }

    /**
     * Who did it: staff name + role, "Name (Nogrgr support)" + impersonation, or System.
     *
     * @return array{name: string, role: string}
     */
    public function actor(AuditEvent $event): array
    {
        $impersonator = is_array($event->changes) && is_numeric($event->changes['impersonator_admin_id'] ?? null) ? (int) $event->changes['impersonator_admin_id'] : null;

        if ($event->guard === 'vendor' && $impersonator !== null) {
            return $this->actors['impersonation#'.$impersonator] ??= [
                'name' => __('identity::vendor_audit.support_name', ['name' => $this->adminName($impersonator)]),
                'role' => __('identity::vendor_audit.impersonation'),
            ];
        }

        if ($event->guard === 'admin' && $event->actor_id !== null) {
            return $this->actors['admin#'.$event->actor_id] ??= [
                'name' => __('identity::vendor_audit.support_name', ['name' => $this->adminName((int) $event->actor_id)]),
                'role' => __('identity::vendor_audit.support'),
            ];
        }

        if ($event->guard === 'vendor' && $event->actor_id !== null) {
            return $this->actors['vendor#'.$event->actor_id] ??= $this->staffActor((int) $event->actor_id);
        }

        return ['name' => __('identity::audit.system_actor'), 'role' => __('identity::vendor_audit.automatic')];
    }

    public function localTime(?Carbon $date): ?Carbon
    {
        return $this->audit->localTime($date, $this->timezone());
    }

    /**
     * Masked address for the store team: 37.x.x.12 / 2001:db8::x.
     */
    public static function maskIp(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '—';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return $parts[0].'.x.x.'.$parts[3];
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $groups = explode(':', $ip);

            return $groups[0].':'.($groups[1] ?? '').'::x';
        }

        return '—';
    }

    public function timezone(): string
    {
        return $this->timezone ??= $this->general->timezone();
    }

    private function adminName(int $id): string
    {
        $admin = $this->admins->find($id);

        return $admin instanceof Admin ? $admin->name : __('identity::vendor_audit.support');
    }

    /**
     * @return array{name: string, role: string}
     */
    private function staffActor(int $id): array
    {
        $member = $this->staff->find($id);

        if (! $member instanceof VendorStaff) {
            return ['name' => __('identity::vendor_audit.former_member', ['id' => $id]), 'role' => __('identity::vendor_audit.removed')];
        }

        $role = $member->getRoleNames()->first();
        $label = $member->is_owner ? __('identity::staff.owner') : (string) (is_string($role) ? Ui::roleLabel($role) : __('identity::staff.no_role'));

        return ['name' => $member->name, 'role' => $label];
    }
}
