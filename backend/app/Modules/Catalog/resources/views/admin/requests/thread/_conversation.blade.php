{{--
    Shared request conversation (ADM-018d / 018f / 018h, Figma 68:31430): messages, "Live"
    indicator (Reverb push, polling fallback every 15 s), composer with Enter-to-send and
    up to 5 attachments. One component for every threaded request type (resources/js/panels/
    admin/requests.js, [data-thread]).
    $request (ThreadedRequest), $messages, $canManage, $realtime (array|null).
--}}
@php
    $prefix = $request->kind()->routePrefix();
    $lastId = (int) ($messages->last()?->id ?? 0);
@endphp
<x-ui::card id="conversation" :padding="false" class="flex min-h-[420px] flex-col"
    data-thread
    :data-messages-url="route($prefix.'.messages', $request->id)"
    :data-last-id="$lastId"
>
    <header class="flex items-center justify-between gap-3 px-5 pt-5">
        <h2 class="text-base font-semibold text-ink">{{ __('catalog::requests.thread.title') }}</h2>
        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-secondary" data-live-state>
            <span class="size-2 rounded-full bg-line-strong" data-live-dot aria-hidden="true"></span>
            <span data-live-label data-live-text="{{ __('catalog::requests.thread.live') }}" data-offline-text="{{ __('catalog::requests.thread.offline') }}">{{ __('catalog::requests.thread.offline') }}</span>
        </span>
    </header>

    <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4" data-thread-scroll aria-live="polite">
        <div @class(['py-6', 'hidden' => $messages->isNotEmpty()]) data-thread-empty>
            <x-ui::empty-state compact icon="message" :title="__('catalog::requests.empty.messages_title')" :description="__('catalog::requests.empty.messages_description')" />
        </div>
        <ol class="grid gap-4" role="list" data-thread-list>
            @foreach ($messages as $message)
                @include('catalog::admin.requests.thread._message', ['message' => $message, 'request' => $request])
            @endforeach
        </ol>
        <p class="mt-3 hidden text-xs text-danger" role="status" data-thread-error>{{ __('catalog::requests.thread.load_error') }}</p>
    </div>

    @if ($canManage)
        <form method="POST" action="{{ route($prefix.'.reply', $request->id) }}" enctype="multipart/form-data" class="grid gap-2 border-t border-line p-4" data-thread-form novalidate>
            @csrf
            <ul class="flex flex-wrap gap-2 empty:hidden" role="list" data-thread-files data-remove-label="{{ __('catalog::requests.thread.remove_file', ['name' => ':name']) }}"></ul>
            <div class="ui-control min-h-12 items-end gap-2 py-1.5">
                <label class="grid size-9 shrink-0 cursor-pointer place-items-center rounded-control text-ink-secondary hover:bg-subtle hover:text-ink" title="{{ __('catalog::requests.thread.attach') }}">
                    <x-ui::icon name="link" :size="18" />
                    <span class="sr-only">{{ __('catalog::requests.thread.attach') }}</span>
                    <input type="file" name="files[]" multiple class="sr-only" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" data-thread-file-input>
                </label>
                <label for="thread-body" class="sr-only">{{ __('catalog::requests.thread.message') }}</label>
                <textarea id="thread-body" name="body" rows="1" maxlength="5000" placeholder="{{ __('catalog::requests.thread.placeholder') }}" class="max-h-40 min-h-9 flex-1 resize-none bg-transparent py-2 text-sm outline-none" data-thread-body></textarea>
                <x-ui::button type="submit" size="sm" icon="arrow-up">{{ __('catalog::requests.thread.send') }}</x-ui::button>
            </div>
            <p class="text-xs text-ink-secondary" data-error-for="body">{{ __('catalog::requests.thread.hint') }}</p>
        </form>
    @else
        <p class="border-t border-line p-4 text-sm text-ink-secondary">{{ __('catalog::requests.thread.read_only') }}</p>
    @endif

    @if ($realtime)
        <script type="application/json" data-thread-realtime>@json($realtime)</script>
    @endif
</x-ui::card>
