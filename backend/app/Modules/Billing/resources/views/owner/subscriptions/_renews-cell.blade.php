{{-- Renews on: next billing date, "Overdue" for a past-due period, — when closed. --}}
@php
    $next = $subscription->nextBillingAt();
@endphp
@if ($subscription->status === \App\Modules\Billing\Enums\SubscriptionStatus::PastDue)
    <span class="font-medium text-danger">{{ __('billing::owner.subscriptions.overdue') }}</span>
@elseif ($next && $subscription->status->isOpen())
    <span class="text-ink">{{ $next->translatedFormat('j M Y') }}</span>
@else
    <span class="text-ink-muted">—</span>
@endif
