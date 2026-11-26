{{-- Vendor "My tickets" row (VEN-091, Figma 88:111731): icon, "T-4410 · subject", category · time, status; links to the thread. --}}
@php
    $time = ($ticket->last_reply_at ?? $ticket->created_at)?->locale(app()->getLocale());
@endphp
<a href="{{ vendor_route('support.show', ['ticket' => $ticket->id]) }}" @class(['-mx-2 flex min-w-0 items-center gap-3 rounded-control px-2 py-1 transition-colors hover:bg-subtle', 'bg-primary-subtle' => $selected]) @if ($selected) aria-current="true" @endif data-ticket-link="{{ $ticket->id }}">
    <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary" aria-hidden="true"><x-ui::icon name="lifebuoy" :size="18" /></span>
    <span class="grid min-w-0 flex-1 gap-0.5">
        <span class="flex items-center gap-1.5 truncate text-sm font-medium text-ink">
            @if ($ticket->requester_unread)
                <span class="size-2 shrink-0 rounded-full bg-primary" role="img" aria-label="{{ __('support::tickets.unread') }}"></span>
            @endif
            <span class="truncate"><bdi dir="ltr">{{ $ticket->displayReference() }}</bdi> · {{ $ticket->subject }}</span>
        </span>
        <span class="truncate text-xs text-ink-secondary">{{ $ticket->category->label() }} · {{ $time?->diffForHumans() }}</span>
    </span>
    <x-ui::badge :tone="$ticket->status->tone()">{{ $ticket->status->requesterLabel() }}</x-ui::badge>
</a>
