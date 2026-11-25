<x-ui::button size="sm" variant="secondary" icon="credit-card" :href="vendor_route('finance.settle.show', ['order' => $uuid])">{{ __('billing::finance.money_flow.pay_platform') }}</x-ui::button>
