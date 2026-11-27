{{-- Cache tools › "last cleared at / by" line of an action ($run: ?CacheClearRun, $key: action value; refreshed in place by system-tools.js). --}}
@if ($run)
    @php
        $time = ($run->finished_at ?? $run->created_at)?->locale(app()->getLocale())->diffForHumans();
        $status = $run->status->label().($run->stores > 0 ? ' · '.__('platform::cache_tools.stores_done', ['count' => \Illuminate\Support\Number::format($run->stores, locale: \App\Shared\Support\Locale::numberLocale())]) : '');
    @endphp
    <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-ink-muted" data-last-run="{{ $key }}">
        <span @class(['size-1.5 rounded-full', 'bg-success' => $run->status->value === 'done', 'bg-info' => $run->status->value === 'queued', 'bg-danger' => $run->status->value === 'failed']) aria-hidden="true"></span>
        {{ $run->admin ? __('platform::cache_tools.last_run', ['status' => $status, 'time' => $time, 'name' => $run->admin->name]) : __('platform::cache_tools.last_run_system', ['status' => $status, 'time' => $time]) }}
        @if ($run->error)
            <span class="text-danger">· {{ $run->error }}</span>
        @endif
    </p>
@else
    <p class="mt-1 text-xs text-ink-muted" data-last-run="{{ $key }}">{{ __('platform::cache_tools.never') }}</p>
@endif
