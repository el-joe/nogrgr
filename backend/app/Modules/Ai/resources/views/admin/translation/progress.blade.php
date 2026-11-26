{{--
    ADM-091b / ADM-092a progress modal body (Admin Panel 79:90884 "Translating catalog →
    <language>"): one bar per section (done / total), failed notice with "Retry now",
    footer actions (Run in background / Stop while active, Close otherwise). Re-rendered
    by admin.localization.ai-translation.runs.show on every Reverb push or poll.
    Data: $run (AdminTranslationRunService::present()), $canManage.
--}}
@use('App\Modules\Ai\Enums\AdminTranslationRunStatus')
@php
    $numberLocale = \App\Shared\Support\Locale::numberLocale();
    $format = fn (int $value): string => \Illuminate\Support\Number::format($value, locale: $numberLocale);
    $title = __('ai::admin_translation.progress.title_'.$run['kind'], ['language' => $run['language']]);
    $status = $run['status'];
@endphp
<div class="grid gap-5" data-ai-run-state data-run="{{ $run['id'] }}" data-run-active="{{ $run['active'] ? '1' : '0' }}" data-run-status="{{ $status->value }}" data-modal-title="{{ $title }}">
    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
        <x-ui::badge :tone="$status->tone()">{{ $status->label() }}</x-ui::badge>
        <bdi class="text-ink-secondary">{{ __('ai::admin_translation.progress.overall', ['done' => $format($run['done']), 'total' => $format($run['total']), 'percent' => $run['progress']]) }}</bdi>
    </div>

    <ul class="grid gap-3.5" role="list">
        @foreach ($run['sections'] as $name => $section)
            @php
                $label = __('ai::admin_translation.sections.'.$name);
                $finished = $section['done'] + $section['failed'];
                $percent = $section['total'] > 0 ? (int) floor($finished * 100 / $section['total']) : ($run['active'] ? 0 : 100);
                $complete = $section['total'] === 0 ? ! $run['active'] : $section['done'] >= $section['total'];
            @endphp
            <li class="grid gap-1.5" data-run-section="{{ $name }}">
                <div class="flex items-center justify-between gap-3">
                    <span class="min-w-0 text-sm font-medium text-ink">{{ $label }}</span>
                    <bdi class="shrink-0 text-xs text-ink-secondary">{{ $format($section['done']) }} / {{ $format($section['total']) }}</bdi>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-subtle" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="{{ __('ai::admin_translation.progress.section_label', ['section' => $label]) }}">
                    <div @class(['h-full rounded-full transition-[width] duration-500', 'bg-success' => $complete, 'bg-primary' => ! $complete]) data-progress="{{ $complete ? 100 : $percent }}"></div>
                </div>
            </li>
        @endforeach
    </ul>

    @if ($run['failed'] > 0 || $run['error'] !== null)
        <div class="flex flex-wrap items-center gap-3 rounded-control bg-danger-bg p-4 text-sm text-ink" role="alert">
            <x-ui::icon name="alert-triangle" :size="18" class="shrink-0 text-danger" />
            <p class="min-w-0 flex-1">
                @if ($run['active'])
                    {{ trans_choice('ai::admin_translation.progress.failed_active', $run['failed'], ['count' => $format($run['failed'])]) }}
                @else
                    {{ $run['error'] ?? trans_choice('ai::admin_translation.errors.items_failed', $run['failed'], ['count' => $format($run['failed'])]) }}
                @endif
            </p>
            @if ($canManage && $status->canRetry())
                <x-ui::button size="sm" variant="secondary" icon="refresh" data-ai-run-retry :data-url="route('admin.localization.ai-translation.runs.retry', $run['id'])">{{ __('ai::admin_translation.progress.retry') }}</x-ui::button>
            @endif
        </div>
    @elseif ($status === AdminTranslationRunStatus::Completed)
        <div class="flex items-center gap-3 rounded-control bg-success-bg p-4 text-sm text-ink" role="status">
            <x-ui::icon name="check-circle" :size="18" class="shrink-0 text-success" />
            <p>{{ $run['total'] > 0 ? __('ai::admin_translation.progress.completed', ['count' => $format($run['done'])]) : __('ai::admin_translation.progress.nothing') }}</p>
        </div>
    @elseif ($status === AdminTranslationRunStatus::Cancelled)
        <div class="flex flex-wrap items-center gap-3 rounded-control bg-subtle p-4 text-sm text-ink" role="status">
            <x-ui::icon name="info" :size="18" class="shrink-0 text-ink-secondary" />
            <p class="min-w-0 flex-1">{{ __('ai::admin_translation.progress.cancelled') }}</p>
            @if ($canManage)
                <x-ui::button size="sm" variant="secondary" icon="refresh" data-ai-run-retry :data-url="route('admin.localization.ai-translation.runs.retry', $run['id'])">{{ __('ai::admin_translation.progress.resume') }}</x-ui::button>
            @endif
        </div>
    @endif

    <div class="-mx-6 -mb-6 flex flex-wrap justify-end gap-3 border-t border-line bg-subtle px-6 py-4">
        @if ($run['active'])
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ai::admin_translation.progress.background') }}</x-ui::button>
            @if ($canManage)
                <x-ui::button variant="danger" data-ai-run-stop :data-url="route('admin.localization.ai-translation.runs.stop', $run['id'])" :data-ai-title="__('ai::admin_translation.progress.stop_title')" :data-ai-message="__('ai::admin_translation.progress.stop_message')" :data-ai-confirm="__('ai::admin_translation.progress.stop')">{{ __('ai::admin_translation.progress.stop') }}</x-ui::button>
            @endif
        @else
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.close') }}</x-ui::button>
        @endif
    </div>
</div>
