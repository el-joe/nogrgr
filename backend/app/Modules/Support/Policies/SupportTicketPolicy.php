<?php

namespace App\Modules\Support\Policies;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\Owner;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Support\Models\SupportTicket;
use App\Modules\Support\Models\SupportTicketAttachment;
use App\Shared\Tenancy\Scopes\CurrentTenantScope;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may read / write a support ticket:
 *  - admins: support.tickets.view reads (internal notes included), .manage writes;
 *  - store staff: their own store's tickets with support.view / support.manage;
 *  - owners: tickets they opened and every ticket of their stores.
 * Attachments of internal notes are admin-only.
 */
class SupportTicketPolicy
{
    public function view(Authenticatable $user, SupportTicket $ticket): bool
    {
        return match (true) {
            $user instanceof Admin => $user->can('support.tickets.view'),
            $user instanceof VendorStaff => $user->can('support.view') && $this->isCurrentStore($ticket),
            $user instanceof Owner => $this->ownsTicket($user, $ticket),
            default => false,
        };
    }

    public function reply(Authenticatable $user, SupportTicket $ticket): bool
    {
        return match (true) {
            $user instanceof Admin => $user->can('support.tickets.manage'),
            $user instanceof VendorStaff => $user->can('support.manage') && $this->isCurrentStore($ticket),
            $user instanceof Owner => $this->ownsTicket($user, $ticket),
            default => false,
        };
    }

    public function viewAttachment(Authenticatable $user, SupportTicketAttachment $attachment): bool
    {
        $ticket = SupportTicket::query()->withoutGlobalScope(CurrentTenantScope::class)->find($attachment->ticket_id);

        if (! $ticket instanceof SupportTicket || ! $this->view($user, $ticket)) {
            return false;
        }

        return $user instanceof Admin || ! (bool) $attachment->message?->is_internal;
    }

    private function isCurrentStore(SupportTicket $ticket): bool
    {
        return tenancy()->initialized && $ticket->tenant_id !== null && (string) $ticket->tenant_id === (string) tenant()?->getTenantKey();
    }

    private function ownsTicket(Owner $owner, SupportTicket $ticket): bool
    {
        if ($ticket->owner_id !== null && (int) $ticket->owner_id === (int) $owner->id) {
            return true;
        }

        return $ticket->tenant_id !== null && $owner->tenants()->whereKey($ticket->tenant_id)->exists();
    }
}
