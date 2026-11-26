{{-- Paid AI translation run of a bought language: queued / translating % / done / failed + retry. --}}
@use('App\Modules\Localization\Enums\TranslationRunStatus')
<div class="grid gap-1.5 ps-1 text-xs text-ink-secondary" data-run-status="{{ $run->status->value }}">
    @switch($run->status)
        @case(TranslationRunStatus::Queued)
            <p>{{ __('localization::vendor_languages.run.queued', ['language' => $name]) }}</p>
            @break
        @case(TranslationRunStatus::Translating)
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-subtle" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $run->progress }}" aria-label="{{ __('localization::vendor_languages.run.progress', ['language' => $name]) }}">
                <div class="h-full rounded-full bg-primary" data-progress="{{ $run->progress }}"></div>
            </div>
            <p>{{ __('localization::vendor_languages.run.translating', ['percent' => $run->progress, 'done' => number_format($run->done_items), 'total' => number_format($run->total_items)]) }}</p>
            @break
        @case(TranslationRunStatus::Completed)
            <p>{{ __('localization::vendor_languages.run.completed', ['language' => $name]) }}</p>
            @break
        @case(TranslationRunStatus::Failed)
            <p class="text-danger">{{ __('localization::vendor_languages.run.failed', ['language' => $name]) }}@if ($run->last_error) — {{ $run->last_error }}@endif</p>
            @if ($canManage)
                <span>
                    <x-ui::button size="sm" variant="secondary" icon="refresh" :href="vendor_route('settings.languages.retry', ['code' => $run->language_code])" data-confirm-method="POST" data-confirm-tone="brand" :data-confirm-title="__('localization::vendor_languages.run.retry')" :data-confirm="__('localization::vendor_languages.run.retry_confirm', ['language' => $name])">{{ __('localization::vendor_languages.run.retry') }}</x-ui::button>
                </span>
            @endif
            @break
    @endswitch
</div>
