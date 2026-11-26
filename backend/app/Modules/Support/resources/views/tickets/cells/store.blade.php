{{-- Store cell: store name + slug, or "Account (no store)" for an owner account ticket. --}}
<span class="grid min-w-0">
    <span class="truncate font-medium text-ink">{{ $ticket->tenant?->getAttribute('name') ?? __('support::tickets.account_ticket') }}</span>
    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $ticket->tenant?->getAttribute('slug') ?? $ticket->opened_by_name }}</span>
</span>
