{{-- Subscriptions history row: billing period (or trial end) of a subscription. --}}
@php
    $format = fn ($date) => $date?->locale(app()->getLocale())->translatedFormat('j M Y');
@endphp
<span class="whitespace-nowrap text-ink" dir="ltr">
    @if ($subscription->status === \App\Modules\Billing\Enums\SubscriptionStatus::Trial)
        {{ __('billing::vendor_subscription.history.trial_until', ['date' => $format($subscription->trial_ends_at)]) }}
    @elseif ($subscription->current_period_starts_at)
        {{ $format($subscription->current_period_starts_at) }} – {{ $format($subscription->current_period_ends_at) }}
    @else
        —
    @endif
</span>
