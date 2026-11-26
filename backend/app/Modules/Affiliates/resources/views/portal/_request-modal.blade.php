{{--
    Request payout modal (AFF-012a, Figma 121:101861 · validation 121:103254 · sheet m
    121:103107) and the vendor "Withdraw" modal (VEN-055a, 87:111915): requestable
    amount, minimum, amount + note. Inputs: $action (POST URL), $overview, $title.
--}}
<x-ui::modal id="payout-request-modal" :title="$title" :subtitle="__('affiliates::panel.payouts.modal_subtitle')" size="sm">
    <form method="POST" action="{{ $action }}" data-ajax class="grid gap-4" novalidate>
        @csrf
        <dl class="grid grid-cols-2 gap-3 rounded-control bg-subtle p-4 text-sm">
            <div class="grid gap-0.5">
                <dt class="text-xs text-ink-secondary">{{ __('affiliates::panel.payouts.requestable') }}</dt>
                <dd class="text-lg font-semibold text-ink" dir="ltr">{{ $overview['requestable']->format() }}</dd>
            </div>
            <div class="grid gap-0.5">
                <dt class="text-xs text-ink-secondary">{{ __('affiliates::panel.payouts.minimum') }}</dt>
                <dd class="text-lg font-semibold text-ink" dir="ltr">{{ $overview['minimum']->format() }}</dd>
            </div>
        </dl>
        <x-ui::input name="amount" type="number" :label="__('affiliates::panel.payouts.amount')" :value="$overview['requestable']->toDecimal()" step="0.01" :min="$overview['minimum']->toDecimal()" :max="$overview['requestable']->toDecimal()" inputmode="decimal" required dir="ltr" :helper="__('affiliates::panel.payouts.amount_hint', ['min' => $overview['minimum']->format(), 'max' => $overview['requestable']->format()])" />
        <x-ui::textarea name="note" :label="__('affiliates::panel.payouts.note')" rows="3" maxlength="1000" />
        <div class="flex flex-wrap justify-end gap-3">
            <x-ui::button variant="secondary" data-overlay-close>{{ __('affiliates::panel.cancel') }}</x-ui::button>
            <x-ui::button type="submit" icon="wallet">{{ __('affiliates::panel.payouts.submit') }}</x-ui::button>
        </div>
    </form>
</x-ui::modal>
