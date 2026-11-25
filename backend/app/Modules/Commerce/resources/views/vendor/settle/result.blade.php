{{-- VEN-021d result: paid / still processing / cancelled or failed with "Try again". --}}
@use('App\Modules\Billing\Enums\PaymentLogStatus')
@php
    $state = match ($log->status) {
        PaymentLogStatus::Captured, PaymentLogStatus::Refunded => 'paid',
        PaymentLogStatus::Pending => 'pending',
        default => 'cancelled',
    };
    $title = __('commerce::settlement.result.'.$state.'_title');
    $orderUrl = vendor_route('sales.orders.show', ['order' => $settlement->order_uuid]);
@endphp
<x-vendor::layout :title="$title" page="vendor-orders">
    <div class="grid gap-6">
        <x-ui::page-header :title="$title" :breadcrumbs="[__('navigation.vendor.orders') => vendor_route('sales.orders.index'), '#'.$settlement->order_number => $orderUrl, $title => null]" />
        <x-ui::card class="max-w-xl" data-payment-state="{{ $state }}">
            <div class="grid gap-5">
                @php $tone = ['paid' => ['bg-success-bg', 'check-circle', 'text-success'], 'pending' => ['bg-warning-bg', 'clock', 'text-warning'], 'cancelled' => ['bg-warning-bg', 'alert-triangle', 'text-warning']][$state]; @endphp
                <p class="flex items-start gap-2 rounded-control {{ $tone[0] }} p-4 text-sm text-ink" role="status">
                    <x-ui::icon :name="$tone[1]" :size="18" class="mt-0.5 shrink-0 {{ $tone[2] }}" />
                    <span>{{ $state === 'cancelled' && $returnedFromCancel ? __('commerce::settlement.result.cancelled_message') : __('commerce::settlement.result.'.$state.'_message', ['amount' => $settlement->money()->format(), 'number' => $settlement->order_number]) }}</span>
                </p>
                <dl class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('commerce::settlement.result.reference') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ \Illuminate\Support\Str::limit((string) ($log->gateway_reference ?? $log->reference()), 24) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('commerce::settlement.due') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $settlement->money()->format() }}</dd></div>
                </dl>
                <div class="flex flex-wrap gap-3">
                    <x-ui::button icon="cart" :href="$orderUrl">{{ __('commerce::settlement.result.back') }}</x-ui::button>
                    @if ($state === 'pending')
                        <x-ui::button variant="secondary" icon="refresh" :href="vendor_route('finance.settle.result', ['payment' => $log->id])">{{ __('commerce::settlement.result.refresh') }}</x-ui::button>
                    @elseif ($state === 'cancelled')
                        <x-ui::button variant="secondary" icon="refresh" :href="vendor_route('finance.settle.show', ['order' => $settlement->order_uuid])">{{ __('commerce::settlement.result.retry') }}</x-ui::button>
                    @endif
                </div>
            </div>
        </x-ui::card>
    </div>
</x-vendor::layout>
