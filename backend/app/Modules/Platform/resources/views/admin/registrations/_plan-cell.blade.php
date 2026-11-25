{{-- Pending registrations › Plan badge (the plan picked on the sign-up form). --}}
@if ($registration->plan)
    <x-ui::badge tone="brand">{{ $registration->plan->name }}</x-ui::badge>
@else
    <x-ui::badge tone="neutral">{{ __('platform::registrations.no_plan') }}</x-ui::badge>
@endif
