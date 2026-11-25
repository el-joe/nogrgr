{{-- Vendors list › Plan badge of the current open subscription. --}}
@if ($name)
    <x-ui::badge tone="brand">{{ $name }}</x-ui::badge>
@else
    <x-ui::badge tone="neutral">{{ __('platform::vendors.no_plan') }}</x-ui::badge>
@endif
