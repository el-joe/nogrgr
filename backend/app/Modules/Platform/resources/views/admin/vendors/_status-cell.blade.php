{{--
    Store status pill. An active store on a trial subscription shows "Trial · N d left"
    (columns subscription_status / trial_ends_at come from TenantRepository::listing()).
--}}
@php
    $isTrial = $tenant->status === \App\Modules\Platform\Enums\TenantStatus::Active
        && $tenant->getAttribute('subscription_status') === \App\Modules\Billing\Enums\SubscriptionStatus::Trial->value;
    $trialEnds = $isTrial && $tenant->getAttribute('trial_ends_at') ? \Illuminate\Support\Carbon::parse($tenant->getAttribute('trial_ends_at')) : null;
@endphp
@if ($isTrial)
    <x-ui::badge tone="warning">
        @if ($trialEnds && $trialEnds->isFuture())
            {{ __('platform::vendors.trial_left', ['days' => (int) ceil(now()->diffInHours($trialEnds) / 24)]) }}
        @elseif ($trialEnds)
            {{ __('platform::vendors.trial_ended') }}
        @else
            {{ __('platform::vendors.tabs.trial') }}
        @endif
    </x-ui::badge>
@else
    <x-ui::badge :tone="$tenant->status->tone()">{{ $tenant->status->label() }}</x-ui::badge>
@endif
