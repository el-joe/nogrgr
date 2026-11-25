{{--
    One conversation message (shared by product / manufacturing / brand requests).
    Vendor: start-aligned grey bubble with the store initials; platform: end-aligned brand
    bubble with a read receipt. Attachments open through the authorized download route.
--}}
@php
    $fromAdmin = $message->isFromAdmin();
    $time = $message->created_at?->locale(app()->getLocale());
    $initials = \Illuminate\Support\Str::of($message->sender_name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<li @class(['flex items-end gap-2', 'justify-end' => $fromAdmin]) data-message-id="{{ $message->id }}">
    @unless ($fromAdmin)
        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary-subtle text-xs font-semibold uppercase text-ink-brand" aria-hidden="true">{{ $initials ?: '?' }}</span>
    @endunless
    <div @class([
        'grid max-w-[85%] gap-2 rounded-card px-3.5 py-2.5 text-sm sm:max-w-[70%]',
        'bg-primary text-on-primary' => $fromAdmin,
        'bg-subtle text-ink' => ! $fromAdmin,
    ])>
        @if (filled($message->body))
            <p class="whitespace-pre-line break-words">{{ $message->body }}</p>
        @endif
        @if ($message->attachments->isNotEmpty())
            <ul class="flex flex-wrap gap-2" role="list">
                @foreach ($message->attachments as $attachment)
                    <li>
                        <a href="{{ route('admin.requests.attachments.show', $attachment->id) }}" target="_blank" rel="noopener" class="inline-flex max-w-[220px] items-center gap-1.5 rounded-control bg-surface px-2.5 py-1.5 text-xs font-medium text-ink shadow-card hover:underline">
                            <x-ui::icon :name="$attachment->isImage() ? 'image' : 'file'" :size="16" class="shrink-0 text-ink-brand" />
                            <span class="truncate">{{ $attachment->original_name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <p @class(['flex items-center gap-1 text-[11px]', 'text-on-primary/80' => $fromAdmin, 'text-ink-secondary' => ! $fromAdmin])>
            <span>{{ $message->sender_name }}</span>
            <span aria-hidden="true">·</span>
            <time datetime="{{ $message->created_at?->toIso8601String() }}" title="{{ $time?->translatedFormat('j M Y, H:i') }}">{{ $time?->isToday() ? $time->translatedFormat('H:i') : $time?->translatedFormat('j M, H:i') }}</time>
            @if ($fromAdmin)
                <span aria-hidden="true">·</span>
                <span data-read-state>{{ $message->read_at ? __('catalog::requests.thread.read') : __('catalog::requests.thread.sent_label') }}</span>
            @endif
        </p>
    </div>
</li>
