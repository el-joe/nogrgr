{{-- Request payment modal: issues a Billing invoice for the vendor. --}}
<x-ui::modal id="payment-request-modal" :title="__('catalog::requests.payments.modal_title')" :subtitle="__('catalog::requests.payments.modal_subtitle')">
    <form id="payment-request-form" method="POST" action="{{ route($request->kind()->routePrefix().'.payments.store', $request->id) }}" data-ajax data-payment-form novalidate class="grid gap-4">
        @csrf
        <x-ui::input name="label" :label="__('catalog::requests.payments.label')" :placeholder="__('catalog::requests.payments.label_placeholder')" maxlength="120" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui::input name="amount" type="number" step="0.01" min="0.01" inputmode="decimal" :label="__('catalog::requests.payments.amount')" required />
            <x-ui::input name="due_at" type="date" :label="__('catalog::requests.payments.due_at')" :min="now()->toDateString()" />
        </div>
        <x-ui::textarea name="note" :label="__('catalog::requests.payments.note')" maxlength="1000" rows="3" />
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
        <x-ui::button type="submit" form="payment-request-form" icon="credit-card">{{ __('catalog::requests.payments.submit') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
