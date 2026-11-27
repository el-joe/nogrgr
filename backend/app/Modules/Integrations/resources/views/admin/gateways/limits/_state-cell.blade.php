{{-- Usage state of a store × gateway this month (ADM-100c). Data: $usage. --}}
@if ($usage->blocked_at)
    <x-ui::badge tone="danger">{{ __('integrations::admin.limits.states.blocked') }}</x-ui::badge>
@elseif ($usage->warned_at)
    <x-ui::badge tone="warning">{{ __('integrations::admin.limits.states.warned') }}</x-ui::badge>
@else
    <x-ui::badge tone="success">{{ __('integrations::admin.limits.states.ok') }}</x-ui::badge>
@endif
