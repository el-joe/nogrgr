{{--
    Live ticket conversation shared by the admin detail (ADM-116a, Figma 80:96112), the
    vendor thread (VEN-091b, 88:111731) and the owner portal: messages, "Live" indicator
    (Reverb push on support.tickets.<id>, 15 s polling fallback), composer with
    Enter-to-send and up to 5 attachments (resources/js/panels/request-thread.js,
    [data-thread]). Admins also get the "Internal note" switch.
    $ticket, $messages, $viewer, $messagesUrl, $replyUrl (null = read only), $realtime,
    optional $title (header), $header (slot-like HTML of actions), $closedNotice (bool).
--}}
@php
    $lastId = (int) ($messages->last()?->id ?? 0);
    $closed = $ticket->status === \App\Modules\Support\Enums\TicketStatus::Closed;
    $canWrite = $replyUrl !== null && ! ($closed && $viewer !== 'admin');
@endphp
<x-ui::card id="conversation" :padding="false" class="flex min-h-[420px] flex-col"
    data-thread
    :data-messages-url="$messagesUrl"
    :data-last-id="$lastId"
>
    <header class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5">
        <h2 class="min-w-0 truncate text-base font-semibold text-ink">{{ $title ?? __('support::tickets.conversation') }}</h2>
        <div class="flex flex-wrap items-center gap-2">
            {{ $actions ?? '' }}
            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-brand" data-live-state>
                <span class="size-2 rounded-full bg-line-strong" data-live-dot aria-hidden="true"></span>
                <span data-live-label data-live-text="{{ __('support::tickets.thread.live') }}" data-offline-text="{{ __('support::tickets.thread.offline') }}">{{ __('support::tickets.thread.offline') }}</span>
            </span>
        </div>
    </header>

    <div class="min-h-0 max-h-[60vh] flex-1 overflow-y-auto px-5 py-4" data-thread-scroll aria-live="polite">
        <div @class(['py-6', 'hidden' => $messages->isNotEmpty()]) data-thread-empty>
            <x-ui::empty-state compact icon="message" :title="__('support::tickets.thread.empty_title')" :description="__('support::tickets.thread.empty_description')" />
        </div>
        <ol class="grid gap-4" role="list" data-thread-list>
            @foreach ($messages as $message)
                @include('support::tickets.thread._message', ['message' => $message, 'ticket' => $ticket, 'viewer' => $viewer])
            @endforeach
        </ol>
        <p class="mt-3 hidden text-xs text-danger" role="status" data-thread-error>{{ __('support::tickets.thread.load_error') }}</p>
    </div>

    @if ($canWrite)
        <form method="POST" action="{{ $replyUrl }}" enctype="multipart/form-data" class="grid gap-2 border-t border-line p-4" data-thread-form novalidate>
            @csrf
            @if ($viewer === 'admin')
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-ui::switch name="internal" id="thread-internal" :label="__('support::tickets.thread.internal')" data-thread-extra data-internal-toggle />
                    <span class="text-xs text-ink-secondary">{{ __('support::tickets.thread.internal_hint') }}</span>
                </div>
            @endif
            <ul class="flex flex-wrap gap-2 empty:hidden" role="list" data-thread-files data-remove-label="{{ __('support::tickets.thread.remove_file', ['name' => ':name']) }}"></ul>
            <div class="ui-control min-h-12 items-end gap-2 py-1.5" data-composer>
                <label class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-control text-ink-secondary hover:bg-subtle hover:text-ink" title="{{ __('support::tickets.thread.attach') }}">
                    <x-ui::icon name="link" :size="18" />
                    <span class="sr-only">{{ __('support::tickets.thread.attach') }}</span>
                    <input type="file" name="files[]" multiple class="sr-only" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" data-thread-file-input>
                </label>
                <label for="thread-body" class="sr-only">{{ __('support::tickets.fields.message') }}</label>
                <textarea id="thread-body" name="body" rows="1" maxlength="5000"
                    placeholder="{{ __('support::tickets.thread.placeholder') }}"
                    data-placeholder-reply="{{ __('support::tickets.thread.placeholder') }}"
                    data-placeholder-note="{{ __('support::tickets.thread.note_placeholder') }}"
                    class="max-h-40 min-h-9 flex-1 resize-none bg-transparent py-2 text-sm outline-none" data-thread-body></textarea>
                <x-ui::button type="submit" size="sm" icon="arrow-up">{{ __('support::tickets.thread.send') }}</x-ui::button>
            </div>
            <p class="text-xs text-ink-secondary" data-error-for="body">{{ __('support::tickets.thread.hint') }}</p>
        </form>
    @elseif ($closed && $replyUrl !== null)
        <p class="border-t border-line p-4 text-sm text-ink-secondary" data-closed-notice>{{ __('support::tickets.thread.closed_notice') }}</p>
    @else
        <p class="border-t border-line p-4 text-sm text-ink-secondary">{{ __('support::tickets.thread.read_only') }}</p>
    @endif

    @if ($realtime)
        <script type="application/json" data-thread-realtime>@json($realtime)</script>
    @endif
</x-ui::card>
