{{--
    Refunds of an order / return as the customer sees them: reference, amount, status,
    method, requested / completed dates and why a refund was rejected.
    $refunds rows: reference, amount (presented money), status, tone, method, requested_at,
    processed_at, rejection_reason.
--}}
@props(['refunds' => []])
@php use App\Modules\Storefront\Services\Account\AccountOrderPages; @endphp
<ul {{ $attributes->class(['grid gap-2']) }}>
    @foreach ($refunds as $refund)
        <li class="grid gap-1 rounded-lg border border-sf-border p-3" data-refund="{{ $refund['reference'] }}">
            <div class="flex flex-wrap items-center gap-2">
                <span class="min-w-0 flex-1 truncate text-sm font-medium text-sf-ink" dir="ltr">{{ $refund['reference'] }}</span>
                <span class="text-sm font-semibold text-sf-ink">{{ AccountOrderPages::money($refund['amount']) }}</span>
                <x-sf::order.badge :tone="$refund['tone']">{{ $refund['status'] }}</x-sf::order.badge>
            </div>
            <p class="text-xs leading-4 text-sf-muted">{{ collect([
                $refund['method'] ?? null,
                ($refund['requested_at'] ?? null) ? __st('orders.refund_requested', ['date' => $refund['requested_at']->translatedFormat('M j, Y')]) : null,
                ($refund['processed_at'] ?? null) ? __st('orders.refund_completed', ['date' => $refund['processed_at']->translatedFormat('M j, Y')]) : null,
            ])->filter()->implode(' · ') }}</p>
            @if (! empty($refund['rejection_reason']))<p class="text-xs leading-4 text-sf-danger">{{ $refund['rejection_reason'] }}</p>@endif
        </li>
    @endforeach
</ul>
