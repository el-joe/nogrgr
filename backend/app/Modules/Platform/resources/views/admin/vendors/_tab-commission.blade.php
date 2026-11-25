{{--
    Vendor profile › Profit & affiliate commission (ADM-041d, Figma 72:51480): vendor
    profit % (platform share = 100 − profit, read-only, updated live) and the affiliate
    commission % on the first payment. Stored as basis points.
--}}
@php
    $percentInput = fn (int $bps) => \App\Modules\Platform\Services\VendorProfileService::percentInput($bps);
@endphp
<x-ui::card :title="__('platform::vendors.commission.title')" :subtitle="__('platform::vendors.commission.hint')" id="vendor-commission" class="max-w-2xl">
    <form id="commission-form" method="POST" action="{{ route('admin.vendors.commission', $tenant->id) }}" @if ($canManage) data-ajax @endif data-commission-form class="grid gap-5" novalidate>
        @csrf
        @method('PUT')
        <x-ui::input name="profit_percent" type="number" step="0.01" min="0" max="100" inputmode="decimal" :label="__('platform::vendors.fields.profit_percent')" :value="$percentInput($tenant->profit_percent_bps)" required dir="ltr" :disabled="! $canManage" data-profit-input>
            <x-slot:suffix><span class="text-sm text-ink-muted">%</span></x-slot:suffix>
        </x-ui::input>
        <x-ui::input name="platform_share" :label="__('platform::vendors.commission.platform_share')" :value="$percentInput(10000 - $tenant->profit_percent_bps)" readonly disabled dir="ltr" data-platform-share>
            <x-slot:suffix><span class="text-sm text-ink-muted">%</span></x-slot:suffix>
        </x-ui::input>
        <x-ui::input name="affiliate_commission_percent" type="number" step="0.01" min="0" max="100" inputmode="decimal" :label="__('platform::vendors.fields.affiliate_commission')" :helper="__('platform::vendors.commission.affiliate_hint')" :value="$percentInput($tenant->affiliate_commission_bps)" required dir="ltr" :disabled="! $canManage">
            <x-slot:suffix><span class="text-sm text-ink-muted">%</span></x-slot:suffix>
        </x-ui::input>
        @if ($canManage)
            <div class="flex justify-end">
                <x-ui::button type="submit" icon="check">{{ __('platform::vendors.commission.save') }}</x-ui::button>
            </div>
        @endif
    </form>
</x-ui::card>
