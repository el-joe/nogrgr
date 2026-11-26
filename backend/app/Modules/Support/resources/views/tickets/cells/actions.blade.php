{{-- Row actions: view; admins with manage also get Resolve (active tickets). --}}
@php
    $url = match ($surface) {
        'owner' => route('owner.support.tickets.show', $ticket->id),
        'vendor' => vendor_route('support.show', ['ticket' => $ticket->id]),
        default => route('admin.support.tickets.show', $ticket->id),
    };
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="$url" :label="__('support::tickets.actions.view', ['reference' => $ticket->displayReference()])" />
    @if ($surface === 'admin' && $canManage && $ticket->status->isActive())
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="check-circle"
                data-confirm-url="{{ route('admin.support.tickets.resolve', $ticket->id) }}"
                data-confirm-method="POST"
                data-confirm-title="{{ __('support::tickets.actions.resolve_title', ['reference' => $ticket->displayReference()]) }}"
                data-confirm="{{ __('support::tickets.actions.resolve_message') }}"
                data-confirm-label="{{ __('support::tickets.actions.resolve') }}"
                data-confirm-tone="brand">{{ __('support::tickets.actions.resolve') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
