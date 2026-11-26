{{-- Ticket cell (admin / owner lists): "T-4410 · subject" linking to the detail, category below; unread dot. --}}
@php
    $url = $surface === 'owner' ? route('owner.support.tickets.show', $ticket->id) : route('admin.support.tickets.show', $ticket->id);
    $unread = $surface === 'admin' ? $ticket->admin_unread : $ticket->requester_unread;
@endphp
<a href="{{ $url }}" class="group grid min-w-0 max-w-[340px] gap-0.5">
    <span class="flex items-center gap-1.5 truncate font-medium text-ink group-hover:text-ink-brand group-hover:underline">
        @if ($unread)
            <span class="size-2 shrink-0 rounded-full bg-primary" role="img" aria-label="{{ __('support::tickets.unread') }}"></span>
        @endif
        <span class="truncate"><bdi dir="ltr">{{ $ticket->displayReference() }}</bdi> · {{ $ticket->subject }}</span>
    </span>
    <span class="text-xs text-ink-secondary">{{ $ticket->category->label() }}</span>
</a>
