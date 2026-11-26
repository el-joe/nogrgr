{{--
    VEN-060b Subscription payment result (Figma Vendor Panel 88:113398): paid ("You're on
    <plan>", amount · gateway · invoice) / still processing (check again) / cancelled or
    failed ("Your plan is unchanged", try again). Inputs: $log, $plan, $term, $invoice,
    $returnedFromCancel.
--}}
@use('App\Modules\Billing\Enums\PaymentLogStatus')
@use('App\Modules\Billing\Enums\PaymentPurpose')
@php
    $state = match ($log->status) {
        PaymentLogStatus::Captured, PaymentLogStatus::Refunded => 'paid',
        PaymentLogStatus::Pending => 'pending',
        default => 'failed',
    };
    $amount = $log->amount()->format();
    $title = match ($state) {
        'paid' => $plan && $log->purpose === PaymentPurpose::Renewal
            ? __('billing::vendor_subscription.result.paid_title', ['plan' => $plan->name])
            : __('billing::vendor_subscription.result.paid_invoice_title'),
        'pending' => __('billing::vendor_subscription.result.pending_title'),
        default => __('billing::vendor_subscription.result.failed_title'),
    };
    $message = match ($state) {
        'paid' => $invoice
            ? __('billing::vendor_subscription.result.paid_with_invoice', ['amount' => $amount, 'gateway' => $log->gateway->label(), 'number' => $invoice->number])
            : __('billing::vendor_subscription.result.paid', ['amount' => $amount, 'gateway' => $log->gateway->label()]),
        'pending' => __('billing::vendor_subscription.result.pending', ['amount' => $amount]),
        default => $returnedFromCancel ? __('billing::vendor_subscription.result.cancelled') : __('billing::vendor_subscription.result.failed'),
    };
    $tone = ['paid' => ['bg-success-bg', 'check-circle', 'text-success'], 'pending' => ['bg-warning-bg', 'clock', 'text-warning'], 'failed' => ['bg-danger-bg', 'alert-triangle', 'text-danger']][$state];
    $subscriptionUrl = vendor_route('finance.subscription');
@endphp
<x-vendor::layout :title="$title" page="vendor-subscription">
    <div class="grid gap-6">
        <x-ui::page-header :title="$title" :breadcrumbs="[__('billing::vendor_subscription.crumb') => null, __('billing::vendor_subscription.title') => $subscriptionUrl, $title => null]" />

        <x-ui::card class="max-w-xl" data-payment-state="{{ $state }}">
            <div class="grid gap-5">
                <p class="flex items-start gap-2 rounded-control {{ $tone[0] }} p-4 text-sm text-ink" role="status">
                    <x-ui::icon :name="$tone[1]" :size="18" class="mt-0.5 shrink-0 {{ $tone[2] }}" />
                    <span>{{ $message }}</span>
                </p>
                <dl class="grid gap-3 text-sm">
                    @if ($plan)
                        <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('billing::vendor_subscription.result.plan') }}</dt><dd class="font-medium text-ink">{{ $plan->name }}@if ($term) · {{ $term->label() }}@endif</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('billing::vendor_subscription.result.amount') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $amount }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-secondary">{{ __('billing::vendor_subscription.result.reference') }}</dt><dd class="font-medium text-ink" dir="ltr">{{ $log->reference() }}</dd></div>
                </dl>
                <div class="flex flex-wrap gap-3">
                    @if ($state === 'paid')
                        <x-ui::button icon="home" :href="vendor_route('dashboard')">{{ __('billing::vendor_subscription.actions.go_dashboard') }}</x-ui::button>
                        @if ($invoice)
                            <x-ui::button variant="secondary" icon="printer" :href="vendor_route('finance.subscription.invoices.print', ['invoice' => $invoice->id])" target="_blank" rel="noopener">{{ __('billing::invoices.print') }}</x-ui::button>
                        @endif
                    @elseif ($state === 'pending')
                        <x-ui::button icon="refresh" :href="vendor_route('finance.subscription.result', ['payment' => $log->id])">{{ __('billing::vendor_subscription.actions.refresh') }}</x-ui::button>
                        <x-ui::button variant="secondary" :href="$subscriptionUrl">{{ __('billing::vendor_subscription.actions.back') }}</x-ui::button>
                    @else
                        <x-ui::button icon="refresh" :href="$subscriptionUrl">{{ __('billing::vendor_subscription.actions.retry') }}</x-ui::button>
                        <x-ui::button variant="secondary" icon="home" :href="vendor_route('dashboard')">{{ __('billing::vendor_subscription.actions.go_dashboard') }}</x-ui::button>
                    @endif
                </div>
            </div>
        </x-ui::card>
    </div>
</x-vendor::layout>
