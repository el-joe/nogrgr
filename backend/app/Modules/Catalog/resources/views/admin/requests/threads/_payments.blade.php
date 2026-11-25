{{-- Payment requests card (Figma 68:33125): label, amount + due/paid line, status, cancel. --}}
@use('App\Modules\Catalog\Enums\PaymentRequestStatus')
@php
    $locale = app()->getLocale();
@endphp
<x-ui::card :title="__('catalog::requests.payments.title')" id="request-payments">
    @if ($canManage)
        <x-slot:actions>
            <x-ui::button size="sm" variant="secondary" icon="plus" data-overlay-open="payment-request-modal">{{ __('catalog::requests.payments.new') }}</x-ui::button>
        </x-slot:actions>
    @endif
    @forelse ($payments as $payment)
        @php
            $line = match ($payment->status) {
                PaymentRequestStatus::Paid => __('catalog::requests.payments.paid_on', ['amount' => $payment->amount->format(), 'date' => $payment->paid_at?->locale($locale)->translatedFormat('j M')]),
                PaymentRequestStatus::Cancelled => __('catalog::requests.payments.cancelled_on', ['amount' => $payment->amount->format(), 'date' => $payment->cancelled_at?->locale($locale)->translatedFormat('j M')]),
                default => __('catalog::requests.payments.due', ['amount' => $payment->amount->format(), 'date' => $payment->due_at?->locale($locale)->translatedFormat('j M') ?? '—']),
            };
        @endphp
        <div class="flex items-center gap-3 border-b border-line py-3 last:border-b-0" data-payment-row="{{ $payment->id }}">
            <span class="grid size-9 shrink-0 place-items-center rounded-control bg-subtle text-ink-secondary"><x-ui::icon name="credit-card" :size="18" /></span>
            <div class="grid min-w-0 flex-1">
                <span class="truncate text-sm font-medium text-ink">{{ $payment->label }}</span>
                <span class="text-xs text-ink-secondary">{{ $line }}</span>
                @if ($payment->invoice)
                    <span class="text-xs text-ink-secondary">{{ __('catalog::requests.payments.invoice', ['number' => $payment->invoice->number]) }}</span>
                @endif
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1">
                <x-ui::badge :tone="$payment->status->tone()">{{ $payment->status->label() }}</x-ui::badge>
                @if ($canManage && $payment->status === PaymentRequestStatus::Pending)
                    <a
                        href="{{ route($request->kind()->routePrefix().'.payments.cancel', ['id' => $request->id, 'payment' => $payment->id]) }}"
                        class="text-xs font-medium text-danger hover:underline"
                        data-confirm="{{ __('catalog::requests.payments.cancel_message') }}"
                        data-confirm-title="{{ __('catalog::requests.payments.cancel_title', ['label' => $payment->label]) }}"
                        data-confirm-method="POST"
                    >{{ __('catalog::requests.payments.cancel') }}</a>
                @endif
            </div>
        </div>
    @empty
        <p class="text-sm text-ink-secondary">{{ __('catalog::requests.empty.payments') }}</p>
    @endforelse
</x-ui::card>
