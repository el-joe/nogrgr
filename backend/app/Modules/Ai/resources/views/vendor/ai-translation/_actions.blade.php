{{-- Run history actions: Run again (latest completed run of a language) / Retry (latest failed run). --}}
@use('App\Modules\Localization\Enums\TranslationRunStatus')
@if ($canManage && $isLatest && $row->status === TranslationRunStatus::Completed)
    <x-ui::button size="sm" variant="secondary" :href="vendor_route('settings.ai-translation.run-again', ['run' => $row->id])" data-confirm-method="POST" data-confirm-tone="brand" :data-confirm-title="__('ai::ai_translation.actions.run_again')" :data-confirm="__('ai::ai_translation.actions.run_again_confirm', ['language' => $name])">{{ __('ai::ai_translation.actions.run_again') }}</x-ui::button>
@elseif ($canManage && $isLatest && $row->status === TranslationRunStatus::Failed)
    <x-ui::button size="sm" variant="secondary" :href="vendor_route('settings.ai-translation.retry', ['run' => $row->id])" data-confirm-method="POST" data-confirm-tone="brand" :data-confirm-title="__('ai::ai_translation.actions.retry')" :data-confirm="__('ai::ai_translation.actions.retry_confirm', ['language' => $name])">{{ __('ai::ai_translation.actions.retry') }}</x-ui::button>
@else
    <span class="text-ink-muted" aria-label="{{ __('ai::ai_translation.actions.none') }}">—</span>
@endif
