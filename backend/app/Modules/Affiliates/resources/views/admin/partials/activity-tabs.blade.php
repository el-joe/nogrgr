{{-- "Affiliates — activity" tabs (Figma 74:67899): conversions, payout requests (pending count), promo codes — only the ones the admin may open. $active, $pending. --}}
@php
    $admin = auth('admin')->user();
    $items = [];
    if ($admin?->can('affiliates.conversions.view')) {
        $items['conversions'] = ['label' => __('affiliates::admin.activity.conversions'), 'href' => route('admin.affiliates.conversions.index')];
    }
    if ($admin?->can('affiliates.payouts.view')) {
        $items['payouts'] = ['label' => __('affiliates::admin.activity.payouts'), 'href' => route('admin.affiliates.payouts.index')] + (($pending ?? 0) > 0 ? ['count' => number_format($pending)] : []);
    }
    if ($admin?->can('affiliates.promo-codes.view')) {
        $items['promo-codes'] = ['label' => __('affiliates::admin.activity.promo_codes'), 'href' => route('admin.affiliates.promo-codes.index')];
    }
@endphp
<x-ui::tabs id="affiliate-activity" :active="$active" :label="__('affiliates::admin.activity.title')" :items="$items" />
