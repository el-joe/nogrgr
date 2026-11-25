{{-- Row actions of VEN-040: own coupons view / edit / delete; central coupons hide / show only. --}}
<div class="flex items-center justify-end gap-1">
    @if ($coupon->isCentral())
        @if ($canManage)
            <x-ui::row-actions>
                <x-ui::dropdown-item :icon="$coupon->hidden ? 'eye' : 'x-circle'" :data-confirm="__($coupon->hidden ? 'commerce::vendor_promotions.unhide' : 'commerce::vendor_promotions.hide')" :data-confirm-title="$coupon->code" data-confirm-tone="primary" :data-confirm-label="__($coupon->hidden ? 'commerce::vendor_promotions.unhide' : 'commerce::vendor_promotions.hide')" data-confirm-method="POST" :data-confirm-url="vendor_route('marketing.coupons.visibility', ['coupon' => $coupon->id, 'hidden' => $coupon->hidden ? 0 : 1])">{{ __($coupon->hidden ? 'commerce::vendor_promotions.unhide' : 'commerce::vendor_promotions.hide') }}</x-ui::dropdown-item>
            </x-ui::row-actions>
        @else
            <span class="text-xs text-ink-secondary">{{ __('commerce::vendor_promotions.read_only') }}</span>
        @endif
    @else
        <x-ui::icon-button icon="eye" size="sm" :label="__('commerce::coupons.view_named', ['code' => $coupon->code])" data-remote-modal="vendor-coupon-view-modal" :data-url="vendor_route('marketing.coupons.show', ['coupon' => $coupon->id])" />
        @if ($canManage)
            <x-ui::icon-button icon="edit" size="sm" :label="__('commerce::coupons.edit_named', ['code' => $coupon->code])" data-remote-modal="vendor-coupon-modal" :data-url="vendor_route('marketing.coupons.edit', ['coupon' => $coupon->id])" />
            <x-ui::row-actions>
                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('commerce::vendor_promotions.coupons.delete_message')" :data-confirm-title="__('commerce::coupons.delete_title', ['code' => $coupon->code])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('marketing.coupons.destroy', ['coupon' => $coupon->id])">{{ __('commerce::promotions.delete') }}</x-ui::dropdown-item>
            </x-ui::row-actions>
        @endif
    @endif
</div>
