{{--
    One ticket message. The reader's side (admin for the admin panel, the store / owner for
    the vendor panel and owner portal) is the end-aligned brand bubble; the other side is
    the start-aligned grey bubble with initials. Internal notes (admin only) are an amber
    bubble with the "Internal" badge. Files open through the viewer's authorised route.
    $message, $ticket, $viewer ('admin' | 'vendor' | 'owner').
--}}
@php
    $viewer ??= 'admin';
    $mine = $viewer === 'admin' ? $message->isFromAdmin() : ! $message->isFromAdmin();
    $internal = (bool) $message->is_internal;
    $attachmentUrl = static fn (int $id): string => match ($viewer) {
        'vendor' => vendor_route('support.attachments.show', ['attachment' => $id]),
        'owner' => route('owner.support.tickets.attachments.show', $id),
        default => route('admin.support.tickets.attachments.show', $id),
    };
    $author = $message->isFromAdmin() && $viewer !== 'admin'
        ? __('support::tickets.mail.signature', ['author' => $message->author_name]).' · '.config('app.name')
        : $message->author_name;
    $time = $message->created_at?->locale(app()->getLocale());
    $initials = \Illuminate\Support\Str::of($message->author_name)->explode(' ')->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
@endphp
<li @class(['flex items-end gap-2', 'justify-end' => $mine]) data-message-id="{{ $message->id }}" @if ($internal) data-internal @endif>
    @unless ($mine)
        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-primary-subtle text-xs font-semibold uppercase text-ink-brand" aria-hidden="true">{{ $initials ?: '?' }}</span>
    @endunless
    <div @class([
        'grid max-w-[85%] gap-2 rounded-card px-3.5 py-2.5 text-sm sm:max-w-[70%]',
        'border border-dashed border-warning/50 bg-warning-bg text-ink' => $internal,
        'bg-primary text-on-primary' => $mine && ! $internal,
        'bg-subtle text-ink' => ! $mine && ! $internal,
    ])>
        @if ($internal)
            <x-ui::badge tone="warning" class="w-fit">{{ __('support::tickets.thread.internal_badge') }}</x-ui::badge>
        @endif
        @if (filled($message->body))
            <p class="whitespace-pre-line break-words">{{ $message->body }}</p>
        @endif
        @if ($message->attachments->isNotEmpty())
            <ul class="flex flex-wrap gap-2" role="list">
                @foreach ($message->attachments as $attachment)
                    <li>
                        <a href="{{ $attachmentUrl($attachment->id) }}" target="_blank" rel="noopener" class="inline-flex max-w-[220px] items-center gap-1.5 rounded-control bg-surface px-2.5 py-1.5 text-xs font-medium text-ink shadow-card hover:underline">
                            <x-ui::icon :name="$attachment->isImage() ? 'image' : 'file'" :size="16" class="shrink-0 text-ink-brand" />
                            <span class="truncate">{{ $attachment->original_name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <p @class(['flex flex-wrap items-center gap-1 text-[11px]', 'text-on-primary/80' => $mine && ! $internal, 'text-ink-secondary' => ! $mine || $internal])>
            <span>{{ $message->isFromAdmin() && $viewer !== 'admin' ? $message->author_name.' ('.config('app.name').')' : $message->author_name }}</span>
            <span aria-hidden="true">·</span>
            <time datetime="{{ $message->created_at?->toIso8601String() }}" title="{{ $time?->translatedFormat('j M Y, H:i') }}">{{ $time?->isToday() ? $time->translatedFormat('H:i') : $time?->translatedFormat('j M, H:i') }}</time>
        </p>
    </div>
</li>
