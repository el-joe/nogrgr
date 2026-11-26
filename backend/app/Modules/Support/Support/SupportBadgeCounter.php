<?php

namespace App\Modules\Support\Support;

use App\Modules\Identity\Models\Admin;
use App\Modules\Support\Repositories\Contracts\SupportTicketRepositoryContract;
use App\Shared\Navigation\Contracts\PanelBadgeCounter;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Admin sidebar: "Support tickets" badge = tickets waiting on the platform (status open).
 */
final class SupportBadgeCounter implements PanelBadgeCounter
{
    public function __construct(private readonly SupportTicketRepositoryContract $tickets) {}

    public function surface(): string
    {
        return 'admin';
    }

    public function counts(?Authenticatable $user): array
    {
        return $user instanceof Admin && $user->can('support.tickets.view') ? ['support' => $this->tickets->countOpenForAdmins()] : [];
    }
}
