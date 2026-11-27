{{--
    ADM-021a / VEN-021a cancel order (Figma 70:41386 / 84:107236), 2 steps: 1) what happens
    (stock released, full refund to the original payment), 2) reason code + note.
--}}
@php $number = $order->number; @endphp
<form id="order-cancel-form" method="POST" action="{{ $action }}" data-ajax novalidate class="grid gap-4"
    data-modal-title="{{ __('commerce::orders_admin.cancel.title', ['number' => $number]) }}"
    x-data="{ step: 1, reason: '' }">
    @csrf
    <div x-show="step === 1" class="grid gap-3">
        <p class="text-sm text-ink">{{ __('commerce::orders_admin.cancel.step1', ['number' => $number]) }}</p>
        <ul class="grid gap-1.5 text-sm text-ink-secondary">
            <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 text-success" />{{ __('commerce::orders_admin.cancel.stock_released') }}</li>
            <li class="flex gap-2"><x-ui::icon name="check" :size="16" class="mt-0.5 text-success" />{{ __('commerce::orders_admin.cancel.customer_notified') }}</li>
        </ul>
        @include('commerce::admin.orders._refund-note', ['preview' => $preview, 'order' => $order])
        <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
            <x-ui::button variant="secondary" data-overlay-close>{{ __('commerce::orders_admin.close') }}</x-ui::button>
            <x-ui::button variant="danger" icon="chevron-right" x-on:click="step = 2">{{ __('commerce::orders_admin.cancel.continue') }}</x-ui::button>
        </div>
    </div>
    <div x-show="step === 2" x-cloak class="grid gap-4">
        <p class="text-xs font-medium uppercase text-ink-secondary">{{ __('commerce::orders_admin.cancel.step', ['step' => 2]) }}</p>
        <x-ui::select name="reason" :label="__('commerce::orders_admin.cancel.reason')" :options="$reasons" :placeholder="__('commerce::orders_admin.cancel.reason_placeholder')" :search="false" required x-on:change="reason = $event.target.value" />
        <x-ui::textarea name="note" :label="isset($vendor) ? __('commerce::orders_admin.cancel.note_customer') : __('commerce::orders_admin.cancel.note')" rows="4" maxlength="500" :helper="__('commerce::orders_admin.cancel.note_hint')" />
        @include('commerce::admin.orders._refund-note', ['preview' => $preview, 'order' => $order])
        <div class="-mx-5 -mb-5 mt-2 flex justify-end gap-2 border-t border-line bg-subtle px-5 py-4 sm:-mx-6 sm:-mb-6 sm:px-6">
            <x-ui::button variant="secondary" icon="chevron-left" x-on:click="step = 1">{{ __('commerce::orders_admin.cancel.back') }}</x-ui::button>
            <x-ui::button type="submit" variant="danger" icon="x-circle">{{ __('commerce::orders_admin.cancel.button') }}</x-ui::button>
        </div>
    </div>
</form>
