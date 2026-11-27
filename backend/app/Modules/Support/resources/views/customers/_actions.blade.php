{{-- Status badge + Resolve / Close / Reopen of a customer ticket (store side). $ticket, $canManage. --}}
@php
    $statusUrl = fn (string $status): string => vendor_route('support.customers.status', ['ticket' => $ticket->id, 'status' => $status]);
    $reference = $ticket->displayReference();
@endphp
<x-ui::badge :tone="$ticket->status->tone()" data-request-status-badge>{{ $ticket->status->label() }}</x-ui::badge>
@if ($canManage)
    @if ($ticket->status->isActive())
        <x-ui::button variant="secondary" size="sm" icon="check-circle"
            :data-confirm-url="$statusUrl('resolved')"
            data-confirm-method="POST"
            :data-confirm-title="__('support::customer_tickets.actions.resolve_title', ['reference' => $reference])"
            :data-confirm="__('support::customer_tickets.actions.resolve_message')"
            :data-confirm-label="__('support::customer_tickets.actions.resolve')"
            data-confirm-tone="brand">{{ __('support::customer_tickets.actions.resolve') }}</x-ui::button>
    @endif
    @unless ($ticket->status->isClosed())
        <x-ui::button variant="ghost" size="sm" icon="x-circle"
            :data-confirm-url="$statusUrl('closed')"
            data-confirm-method="POST"
            :data-confirm-title="__('support::customer_tickets.actions.close_title', ['reference' => $reference])"
            :data-confirm="__('support::customer_tickets.actions.close_message')"
            :data-confirm-label="__('support::customer_tickets.actions.close')"
            data-confirm-tone="brand">{{ __('support::customer_tickets.actions.close') }}</x-ui::button>
    @endunless
    @unless ($ticket->status->isActive())
        <x-ui::button variant="secondary" size="sm" icon="refresh"
            :data-confirm-url="$statusUrl('open')"
            data-confirm-method="POST"
            :data-confirm-title="__('support::customer_tickets.actions.reopen_title', ['reference' => $reference])"
            :data-confirm="__('support::customer_tickets.actions.reopen_message')"
            :data-confirm-label="__('support::customer_tickets.actions.reopen')"
            data-confirm-tone="brand">{{ __('support::customer_tickets.actions.reopen') }}</x-ui::button>
    @endunless
@endif
