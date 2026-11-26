{{-- Status pill of a translation run (client State set 12:40366): queued / running / completed (translated on the languages card) / failed. --}}
@use('App\Modules\Localization\Enums\TranslationRunStatus')
@php
    $tone = match ($status) {
        TranslationRunStatus::Queued => 'warning',
        TranslationRunStatus::Translating => 'brand',
        TranslationRunStatus::Completed => 'success',
        TranslationRunStatus::Failed => 'danger',
    };
    $key = $status === TranslationRunStatus::Completed && ($context ?? 'history') === 'language' ? 'translated' : $status->value;
@endphp
<x-ui::badge :tone="$tone" dot data-run-status="{{ $status->value }}">{{ __('ai::ai_translation.status.'.$key) }}</x-ui::badge>
