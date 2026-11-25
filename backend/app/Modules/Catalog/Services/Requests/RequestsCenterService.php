<?php

namespace App\Modules\Catalog\Services\Requests;

use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Identity\Models\Admin;

/**
 * Requests center hub (ADM-018): the tabs an admin may open, with the number of items
 * that need attention in each (unread / never-opened threads, pending reviews). Also feeds
 * the sidebar badges ("requests" + one per child).
 */
class RequestsCenterService
{
    /**
     * Tab key => [route, permission].
     *
     * @var array<string, array{route: string, permission: string}>
     */
    public const TABS = [
        'products' => ['route' => 'admin.requests.products.index', 'permission' => 'requests.products.view'],
        'edits' => ['route' => 'admin.requests.edits.index', 'permission' => 'requests.edits.view'],
        'manufacturing' => ['route' => 'admin.requests.manufacturing.index', 'permission' => 'requests.manufacturing.view'],
        'brands' => ['route' => 'admin.requests.brands.index', 'permission' => 'requests.brands.view'],
        'tenant-changes' => ['route' => 'admin.requests.tenant-changes.index', 'permission' => 'requests.tenant-changes.view'],
    ];

    public function __construct(
        private readonly ThreadedRequestService $threaded,
        private readonly EditRequestService $edits,
        private readonly TenantChangeService $tenantChanges,
    ) {}

    /**
     * Visible tabs with their label, url and attention count.
     *
     * @return array<string, array{label: string, href: string, count: int}>
     */
    public function tabs(Admin $admin): array
    {
        $tabs = [];

        foreach (self::TABS as $key => $tab) {
            if ($admin->can($tab['permission'])) {
                $tabs[$key] = [
                    'label' => (string) __('catalog::requests.tabs.'.$key),
                    'href' => route($tab['route']),
                    'count' => $this->count($key),
                ];
            }
        }

        return $tabs;
    }

    /**
     * First tab the admin may open (hub redirect), or null.
     */
    public function firstRoute(Admin $admin): ?string
    {
        foreach (self::TABS as $tab) {
            if ($admin->can($tab['permission'])) {
                return route($tab['route']);
            }
        }

        return null;
    }

    /**
     * Sidebar badge counts: "requests.<tab>" per permitted tab and "requests" = total.
     *
     * @return array<string, int>
     */
    public function badgeCounts(Admin $admin): array
    {
        $counts = [];
        $total = 0;

        foreach (self::TABS as $key => $tab) {
            if ($admin->can($tab['permission'])) {
                $counts['requests.'.$key] = $count = $this->count($key);
                $total += $count;
            }
        }

        return $counts === [] ? [] : ['requests' => $total, ...$counts];
    }

    public function count(string $tab): int
    {
        return match ($tab) {
            'products' => $this->threaded->adminUnreadCount(RequestKind::Product),
            'manufacturing' => $this->threaded->adminUnreadCount(RequestKind::Manufacturing),
            'brands' => $this->threaded->adminUnreadCount(RequestKind::Brand),
            'edits' => $this->edits->pendingCount(),
            'tenant-changes' => $this->tenantChanges->pendingCount(),
            default => 0,
        };
    }
}
