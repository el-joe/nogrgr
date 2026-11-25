{{--
    Request payment result (VEN-050j, Vendor Panel (new screens) 87:110730): paid (success
    alert, gateway reference, invoice, "Back to request"), still processing (check again)
    or cancelled / failed ("nothing was charged" + Try again).
    $log (PaymentLog), $request (ThreadedRequest), $payment (RequestPayment), $returnedFromCancel.
--}}
@use('App\Modules\Billing\Enums\PaymentLogStatus')
@use('App\Modules\Catalog\Enums\PaymentRequestStatus')
@php
    $state = match ($log->status) {
        PaymentLogStatus::Captured, PaymentLogStatus::Refunded => 'paid',
        PaymentLogStatus::Pending => 'pending',
        default => 'cancelled',
    };
    $amount = $log->amount()->format();
    $showUrl = vendor_route('requests.'.$request->kind()->value.'.show', ['id' => $request->id]);
    $title = __('catalog::vendor_requests.result.'.$state.'_title');
    $canRetry = $state === 'cancelled' && (bool) auth('vendor')->user()?->can($request->kind()->permission('manage')) && $payment->status === PaymentRequestStatus::Pending && $gateways->isNotEmpty();
@endphp
<x-vendor::layout :title="$title" page="vendor-requests">
    <div class="grid gap-6">
        <x-ui::page-header :title="$title" :breadcrumbs="[__('catalog::vendor_requests.title') => vendor_route('requests.index'), $request->reference() => $showUrl, $title => null]">
            <x-slot:status>
                <x-ui::badge :tone="['paid' => 'success', 'pending' => 'warning', 'cancelled' => 'danger'][$state]" dot>{{ __('catalog::vendor_requests.result.'.$state) }}</x-ui::badge>
            </x-slot:status>
        </x-ui::page-header>

        <x-ui::card class="max-w-xl" id="payment-result" data-payment-state="{{ $state }}">
            <div class="grid gap-5">
                @if ($state === 'paid')
                    <p class="flex items-start gap-2 rounded-control bg-success-bg p-4 text-sm text-ink" role="status">
                        <x-ui::icon name="check-circle" :size="18" class="mt-0.5 shrink-0 text-success" />
                        <span>{{ __('catalog::vendor_requests.result.paid_message', ['amount' => $amount, 'reference' => $request->reference()]) }}</span>
                    </p>
                @elseif ($state === 'pending')
                    <p class="flex items-start gap-2 rounded-control bg-warning-bg p-4 text-sm text-ink" role="status">
                        <x-ui::icon name="clock" :size="18" class="mt-0.5 shrink-0 text-warning" />
                        <span>{{ __('catalog::vendor_requests.result.pending_message', ['amount' => $amount, 'reference' => $request->reference()]) }}</span>
                    </p>
                @else
                    <p class="flex items-start gap-2 rounded-control bg-warning-bg p-4 text-sm text-ink" role="alert">
                        <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-warning" />
                        <span>{{ $returnedFromCancel ? __('catalog::vendor_requests.result.cancelled_message') : __('catalog::vendor_requests.result.failed_message') }}</span>
                    </p>
                @endif

                <dl class="grid gap-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor_requests.result.reference') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ \Illuminate\Support\Str::limit((string) ($log->gateway_reference ?? $log->reference()), 24) }}</dd></div>
                    @if ($log->invoice)
                        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('catalog::vendor_requests.result.invoice') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $log->invoice->number }}</dd></div>
                    @endif
                </dl>

                <div class="flex flex-wrap gap-3">
                    <x-ui::button icon="message" :href="$showUrl">{{ __('catalog::vendor_requests.result.back') }}</x-ui::button>
                    @if ($state === 'pending')
                        <x-ui::button variant="secondary" icon="refresh" :href="vendor_route('requests.payment.result', ['payment' => $log->id])">{{ __('catalog::vendor_requests.result.refresh') }}</x-ui::button>
                    @endif
                    @if ($canRetry)
                        <form method="POST" action="{{ vendor_route('requests.'.$request->kind()->value.'.payments.pay', ['id' => $request->id, 'payment' => $payment->id]) }}">
                            @csrf
                            <input type="hidden" name="gateway" value="{{ $log->gateway->value }}">
                            <x-ui::button type="submit" variant="secondary" icon="refresh">{{ __('catalog::vendor_requests.result.try_again') }}</x-ui::button>
                        </form>
                    @endif
                </div>
            </div>
        </x-ui::card>
    </div>
</x-vendor::layout>
