<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Services\Requests\VendorRequestService;
use App\Modules\Identity\Models\VendorStaff;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Vendor sidebar: "Requests" badge + one per request type (requests with unread replies
 * from the platform).
 */
final class VendorRequestsBadgeCounter implements PanelBadgeCounter
{
    public function __construct(private readonly VendorRequestService $requests) {}

    public function surface(): string
    {
        return 'vendor';
    }

    public function counts(?Authenticatable $user): array
    {
        return $user instanceof VendorStaff && tenancy()->initialized ? $this->requests->badgeCounts($user) : [];
    }
}
