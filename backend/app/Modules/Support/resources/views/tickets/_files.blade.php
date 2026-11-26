{{-- Files shared on a ticket (download through the viewer's authorised route). $attachments, $url (Closure int → string). --}}
<x-ui::card :title="__('support::tickets.files_title', ['count' => $attachments->count()])" id="ticket-files">
    @forelse ($attachments as $attachment)
        <a href="{{ $url($attachment->id) }}" class="flex items-center gap-3 border-b border-line py-2.5 last:border-b-0 hover:bg-subtle" aria-label="{{ $attachment->original_name }}">
            <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon :name="$attachment->isImage() ? 'image' : 'file'" :size="18" /></span>
            <span class="grid min-w-0">
                <span class="truncate text-sm font-medium text-ink">{{ $attachment->original_name }}</span>
                <span class="text-xs text-ink-secondary">{{ $attachment->humanSize() }}</span>
            </span>
            <x-ui::icon name="download" :size="16" class="ms-auto shrink-0 text-ink-secondary" />
        </a>
    @empty
        <p class="text-sm text-ink-secondary">{{ __('support::tickets.no_files') }}</p>
    @endforelse
</x-ui::card>
