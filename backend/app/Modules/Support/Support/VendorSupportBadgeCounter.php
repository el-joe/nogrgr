<?php

namespace App\Modules\Support\Support;

use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Support\Repositories\Contracts\SupportTicketRepositoryContract;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Vendor sidebar: "Support" badge = the store's tickets with an unread platform reply.
 */
final class VendorSupportBadgeCounter implements PanelBadgeCounter
{
    public function __construct(private readonly SupportTicketRepositoryContract $tickets) {}

    public function surface(): string
    {
        return 'vendor';
    }

    public function counts(?Authenticatable $user): array
    {
        return $user instanceof VendorStaff && tenancy()->initialized && $user->can('support.view')
            ? ['vendor-support' => $this->tickets->countUnreadForRequester()]
            : [];
    }
}
