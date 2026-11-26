{{--
    Current run card body (VEN-074, Figma 180:28706 "Current run"): overall bar from the
    run (done / total items), one bar per page section (AiTranslationOverviewService::
    SECTION_GROUPS, item counts of store_translation_items vs the registry sections),
    failed-items notice + list, live note. Re-rendered by the status endpoint.
--}}
@use('App\Modules\Localization\Enums\TranslationRunStatus')
@use('App\Shared\Support\Locale')
@php
    $run = $current['run'];
    $numberLocale = Locale::numberLocale();
    $format = fn (int $value): string => \Illuminate\Support\Number::format($value, locale: $numberLocale);
@endphp
<div class="grid gap-5" data-run-current="{{ $run?->id }}" data-run-active="{{ $run?->status->isActive() ? '1' : '0' }}">
    @if ($run === null)
        <x-ui::empty-state compact icon="languages" :title="__('ai::ai_translation.current.empty_title')" :description="__('ai::ai_translation.current.empty_description')" />
    @else
        @php
            $tokens = $run->prompt_tokens + $run->completion_tokens;
            $overall = $run->status === TranslationRunStatus::Completed ? 100 : $run->progress;
        @endphp
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="grid min-w-0 gap-1">
                <h2 class="text-xl font-semibold text-ink sm:text-2xl">{{ __($run->status->isActive() ? 'ai::ai_translation.current.title' : 'ai::ai_translation.current.title_last', ['language' => $current['name']]) }}</h2>
                <p class="text-sm text-ink-secondary">
                    @if ($run->status === TranslationRunStatus::Queued)
                        {{ __('ai::ai_translation.current.queued', ['date' => ($run->queued_at ?? $run->created_at)?->translatedFormat('j M, H:i')]) }}
                    @elseif ($run->status === TranslationRunStatus::Translating)
                        {{ __('ai::ai_translation.current.started', ['date' => $run->started_at?->translatedFormat('j M, H:i') ?? '—', 'tokens' => $format($tokens)]) }}
                    @else
                        {{ __('ai::ai_translation.current.finished', ['date' => $run->finished_at?->translatedFormat('j M, H:i') ?? '—', 'tokens' => $format($tokens)]) }}
                    @endif
                </p>
            </div>
            @include('ai::vendor.ai-translation._status', ['status' => $run->status, 'context' => 'history'])
        </header>

        <div class="grid gap-2">
            <div class="flex items-center justify-between gap-3 text-sm">
                <span class="font-semibold text-ink">{{ __('ai::ai_translation.current.overall') }}</span>
                <bdi class="text-ink-secondary">{{ __('ai::ai_translation.current.overall_value', ['done' => $format($run->done_items), 'total' => $format($run->total_items), 'percent' => $overall]) }}</bdi>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-subtle" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $overall }}" aria-label="{{ __('ai::ai_translation.current.overall') }}">
                <div class="h-full rounded-full bg-primary transition-[width] duration-500" data-progress="{{ $overall }}"></div>
            </div>
        </div>

        <ul class="grid gap-4" role="list">
            @foreach ($current['sections'] as $section)
                @php $label = __('ai::ai_translation.current.sections.'.$section['key']); @endphp
                <li class="grid gap-2" data-run-section="{{ $section['key'] }}">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="min-w-0 text-ink">{{ $label }}</span>
                        <bdi class="shrink-0 text-ink-secondary">{{ __('ai::ai_translation.current.'.$section['state'], ['done' => $format($section['done']), 'total' => $format($section['total'])]) }}</bdi>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-subtle" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $section['state'] === 'done' ? 100 : $section['percent'] }}" aria-label="{{ __('ai::ai_translation.current.progress_label', ['section' => $label]) }}">
                        <div @class(['h-full rounded-full transition-[width] duration-500', 'bg-success' => $section['state'] === 'done', 'bg-primary' => $section['state'] !== 'done']) data-progress="{{ $section['state'] === 'done' ? 100 : $section['percent'] }}"></div>
                    </div>
                </li>
            @endforeach
        </ul>

        @if ($run->failed_items > 0 || $current['failed']->isNotEmpty())
            @php $failedCount = max($run->failed_items, $current['failed']->count()); @endphp
            <details class="group rounded-control bg-danger-bg px-3 py-2.5 text-[13px] text-danger" data-run-failed>
                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2">
                    <span>{{ trans_choice('ai::ai_translation.current.failed_notice', $failedCount, ['count' => $format($failedCount)]) }}</span>
                    <span class="font-medium text-ink-brand underline-offset-2 group-open:underline">{{ __('ai::ai_translation.current.view_failed') }}</span>
                </summary>
                @if ($run->last_error)
                    <p class="mt-2">{{ __('ai::ai_translation.current.error', ['error' => $run->last_error]) }}</p>
                @endif
                @if ($current['failed']->isNotEmpty())
                    <ul class="mt-2 grid max-h-56 gap-1 overflow-y-auto text-ink" role="list">
                        @foreach ($current['failed'] as $item)
                            <li class="grid rounded-control bg-surface px-2.5 py-1.5">
                                <span class="font-medium">{{ __('ai::ai_translation.current.failed_item', ['section' => $item->section->label(), 'key' => $item->item_key]) }}</span>
                                @if ($item->error)
                                    <span class="text-xs text-ink-secondary">{{ $item->error }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($current['failed']->count() >= \App\Modules\Ai\Services\StoreTranslation\AiTranslationOverviewService::FAILED_LIST_LIMIT)
                        <p class="mt-1 text-xs">{{ __('ai::ai_translation.current.failed_more', ['count' => $current['failed']->count()]) }}</p>
                    @endif
                @endif
            </details>
        @endif

        <p class="flex items-center gap-2 text-xs text-ink-muted">
            <span class="size-2 shrink-0 rounded-full bg-success"></span>{{ __('ai::ai_translation.current.live') }}
        </p>
    @endif
</div>
