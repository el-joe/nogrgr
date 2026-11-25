@php $missing = $template->missingLocales(); @endphp
@if ($template->status === \App\Modules\Notifications\Enums\EmailTemplateStatus::Inactive)
    <x-ui::badge tone="neutral">{{ $template->status->label() }}</x-ui::badge>
@elseif ($missing !== [])
    <x-ui::badge tone="warning">{{ __('notifications::templates.missing_locale', ['locale' => strtoupper(implode(', ', $missing))]) }}</x-ui::badge>
@else
    <x-ui::badge tone="success">{{ $template->status->label() }}</x-ui::badge>
@endif
