<?php

namespace App\Modules\Catalog\Support;

use App\Modules\Catalog\Services\Requests\RequestsCenterService;
use App\Modules\Identity\Models\Admin;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Admin sidebar: Requests center badge + one per tab (items needing attention).
 */
final class RequestsBadgeCounter implements PanelBadgeCounter
{
    public function __construct(private readonly RequestsCenterService $center) {}

    public function surface(): string
    {
        return 'admin';
    }

    public function counts(?Authenticatable $user): array
    {
        return $user instanceof Admin ? $this->center->badgeCounts($user) : [];
    }
}
