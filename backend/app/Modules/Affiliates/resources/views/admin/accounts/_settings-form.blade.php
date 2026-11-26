{{-- ADM-060c "Affiliate program settings" (Figma 74:68877): cookie days, default commission (%), minimum payout (USD). $cookieDays, $defaultCommission, $minPayout. --}}
<form id="affiliate-settings-form" method="POST" action="{{ route('admin.affiliates.settings.update') }}" data-ajax class="grid gap-4"
    data-modal-title="{{ __('affiliates::admin.settings.title') }}">
    @csrf
    @method('PUT')
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui::input name="cookie_days" type="number" min="1" max="365" :value="$cookieDays" :label="__('affiliates::admin.settings.cookie_days')" required dir="ltr" />
        <x-ui::input name="default_commission" type="number" step="0.01" min="0" max="100" :value="rtrim(rtrim(number_format($defaultCommission, 2, '.', ''), '0'), '.')" :label="__('affiliates::admin.settings.default_commission')" required dir="ltr" />
    </div>
    <x-ui::input name="min_payout" type="number" step="0.01" min="0" :value="$minPayout->toDecimal()" :label="__('affiliates::admin.settings.min_payout')" :helper="__('affiliates::admin.settings.help')" required dir="ltr" />
    @include('commerce::partials.modal-actions', ['submit' => __('affiliates::admin.save'), 'cancel' => __('affiliates::admin.cancel')])
</form>
