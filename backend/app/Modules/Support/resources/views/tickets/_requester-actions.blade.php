{{-- Status badge + Close / Reopen of the store / owner side of a ticket. $ticket, $canManage, $closeUrl, $reopenUrl. --}}
<x-ui::badge :tone="$ticket->status->tone()" data-request-status-badge>{{ $ticket->status->requesterLabel() }}</x-ui::badge>
@if ($canManage)
    @if ($ticket->status->isActive())
        <x-ui::button variant="ghost" size="sm" icon="x-circle"
            :data-confirm-url="$closeUrl"
            data-confirm-method="POST"
            :data-confirm-title="__('support::tickets.actions.close_title', ['reference' => $ticket->displayReference()])"
            :data-confirm="__('support::tickets.actions.close_message')"
            :data-confirm-label="__('support::tickets.actions.close')"
            data-confirm-tone="brand">{{ __('support::tickets.actions.close') }}</x-ui::button>
    @else
        <x-ui::button variant="secondary" size="sm" icon="refresh"
            :data-confirm-url="$reopenUrl"
            data-confirm-method="POST"
            :data-confirm-title="__('support::tickets.actions.reopen_title', ['reference' => $ticket->displayReference()])"
            :data-confirm="__('support::tickets.actions.reopen_message')"
            :data-confirm-label="__('support::tickets.actions.reopen')"
            data-confirm-tone="brand">{{ __('support::tickets.actions.reopen') }}</x-ui::button>
    @endif
@endif
