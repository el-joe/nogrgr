{{--
    "Import in progress" card (Figma 65:22120): title by status, driver / start line,
    status badge, Products + Images bars and the Created / Updated / Skipped / Errors
    tiles. Live-updated by catalog-import.js from the progress endpoint.
    @param \App\Modules\Catalog\Models\CatalogImportRun $run, array $progress
--}}
@php
    $time = $run->started_at ?? $run->created_at;
    $meta = match (true) {
        $run->status === \App\Modules\Catalog\Enums\ImportStatus::Queued => __('catalog::imports.progress.queued_meta', ['driver' => $run->driver->label()]),
        $run->starter !== null => __('catalog::imports.progress.meta', ['driver' => $run->driver->label(), 'time' => $time?->format('H:i'), 'name' => $run->starter->name]),
        default => __('catalog::imports.progress.meta_system', ['driver' => $run->driver->label(), 'time' => $time?->format('H:i')]),
    };
    $tiles = [
        'created' => 'text-ink',
        'updated' => 'text-ink',
        'skipped' => 'text-warning',
        'errors' => 'text-danger',
    ];
@endphp
<x-ui::card id="import-progress" aria-live="polite">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="grid min-w-0 gap-0.5">
            <h2 class="text-base font-semibold text-ink">
                @foreach (\App\Modules\Catalog\Enums\ImportStatus::cases() as $case)
                    <span data-status-value="{{ $case->value }}" @if ($case !== $run->status) hidden @endif>{{ __('catalog::imports.progress.'.$case->value) }}</span>
                @endforeach
            </h2>
            <p class="text-xs text-ink-secondary">{{ $meta }}</p>
        </div>
        <span>
            @foreach (\App\Modules\Catalog\Enums\ImportStatus::cases() as $case)
                <span data-status-value="{{ $case->value }}" @if ($case !== $run->status) hidden @endif>@include('catalog::admin.imports._status', ['status' => $case])</span>
            @endforeach
        </span>
    </div>

    <div class="grid gap-4">
        @include('catalog::admin.imports._bar', ['key' => 'products', 'label' => __('catalog::imports.progress.products'), 'value' => $progress['products']])
        @include('catalog::admin.imports._bar', ['key' => 'images', 'label' => __('catalog::imports.progress.images'), 'value' => $progress['images']])
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ($tiles as $key => $tone)
            <div class="grid gap-1 rounded-control bg-subtle px-4 py-3">
                <span class="text-xs text-ink-secondary">{{ __('catalog::imports.progress.counts.'.$key) }}</span>
                <bdi dir="ltr" class="text-xl font-semibold tabular-nums {{ $tone }}" data-progress-count="{{ $key }}">{{ number_format($progress['counts'][$key]) }}</bdi>
            </div>
        @endforeach
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-ink-secondary">
        <span data-progress-pages data-template="{{ __('catalog::imports.progress.pages', ['done' => '__done__', 'total' => '__total__']) }}">{{ __('catalog::imports.progress.pages', ['done' => number_format($progress['pages']['done']), 'total' => number_format($progress['pages']['total'])]) }}</span>
        <span data-progress-live @if (! $progress['active']) hidden @endif class="inline-flex items-center gap-1.5"><x-ui::spinner :size="14" /> {{ __('catalog::imports.progress.live') }}</span>
        @if ($run->finished_at)
            <span>{{ __('catalog::imports.progress.finished', ['time' => $run->finished_at->locale(app()->getLocale())->translatedFormat('j M Y, H:i')]) }}</span>
        @endif
    </div>

    <p data-progress-failure class="mt-4 rounded-control bg-danger-bg px-4 py-3 text-sm text-danger" @if (blank($progress['failure'])) hidden @endif>{{ $progress['failure'] }}</p>
    <p data-progress-poll-error class="mt-4 rounded-control bg-warning-bg px-4 py-3 text-sm text-warning" hidden>{{ __('catalog::imports.progress.poll_error') }}</p>
</x-ui::card>
