<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('commerce::coupons.view_named', ['code' => $coupon->code])" data-remote-modal="coupon-view-modal" :data-url="route('admin.promotions.coupons.show', $coupon)" />
    @if (auth('admin')->user()?->can('coupons.manage'))
        <x-ui::icon-button icon="edit" size="sm" :label="__('commerce::coupons.edit_named', ['code' => $coupon->code])" data-remote-modal="coupon-modal" :data-url="route('admin.promotions.coupons.edit', $coupon)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('commerce::coupons.delete_message')" :data-confirm-title="__('commerce::coupons.delete_title', ['code' => $coupon->code])" data-confirm-method="DELETE" :data-confirm-url="route('admin.promotions.coupons.destroy', $coupon)">{{ __('commerce::promotions.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
