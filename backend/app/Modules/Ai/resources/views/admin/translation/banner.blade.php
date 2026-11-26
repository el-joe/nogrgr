{{--
    Latest admin AI translation run of a page (Admin › Languages: catalogue, translations
    editor: static keys) while it runs, or when it failed / was stopped: status, progress
    and "View progress" (opens the progress modal, where it can be retried).
    Data: $latest (present() array or null).
--}}
@php($latest ??= null)
@if ($latest !== null && ($latest['active'] || $latest['status']->canRetry()))
    <div @class([
        'flex flex-wrap items-center gap-3 rounded-control p-3 text-sm text-ink',
        'bg-info-bg' => $latest['active'],
        'bg-danger-bg' => ! $latest['active'],
    ]) role="status" data-ai-run-banner>
        <x-ui::icon :name="$latest['active'] ? 'sparkles' : 'alert-triangle'" :size="18" @class(['shrink-0', 'text-info' => $latest['active'], 'text-danger' => ! $latest['active']]) />
        <p class="min-w-0 flex-1">
            {{ __('ai::admin_translation.banner.'.($latest['active'] ? 'active' : $latest['status']->value), ['kind' => __('ai::admin_translation.kinds.'.$latest['kind']), 'language' => $latest['language'], 'percent' => $latest['progress']]) }}
        </p>
        <x-ui::button size="sm" variant="secondary" data-ai-run-open :data-url="route('admin.localization.ai-translation.runs.show', $latest['id'])" :data-active="$latest['active'] ? '1' : '0'">{{ __('ai::admin_translation.banner.view') }}</x-ui::button>
    </div>
@endif
