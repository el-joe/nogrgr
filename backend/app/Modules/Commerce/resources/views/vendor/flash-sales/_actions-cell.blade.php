{{-- Row actions of VEN-041: own sales view / edit / delete; central sales hide / show only. --}}
<div class="flex items-center justify-end gap-1">
    @if ($sale->source === \App\Modules\Commerce\Enums\DiscountSource::Central)
        @if ($canManage)
            <x-ui::row-actions>
                <x-ui::dropdown-item :icon="$sale->hidden ? 'eye' : 'x-circle'" :data-confirm="__($sale->hidden ? 'commerce::vendor_promotions.unhide' : 'commerce::vendor_promotions.hide')" :data-confirm-title="$sale->title" data-confirm-tone="primary" :data-confirm-label="__($sale->hidden ? 'commerce::vendor_promotions.unhide' : 'commerce::vendor_promotions.hide')" data-confirm-method="POST" :data-confirm-url="vendor_route('marketing.flash-sales.visibility', ['flashSale' => $sale->id, 'hidden' => $sale->hidden ? 0 : 1])">{{ __($sale->hidden ? 'commerce::vendor_promotions.unhide' : 'commerce::vendor_promotions.hide') }}</x-ui::dropdown-item>
            </x-ui::row-actions>
        @else
            <span class="text-xs text-ink-secondary">{{ __('commerce::vendor_promotions.read_only') }}</span>
        @endif
    @else
        <x-ui::icon-button icon="eye" size="sm" :label="__('commerce::flash_sales.view_named', ['title' => $sale->title])" data-remote-modal="vendor-flash-sale-view-modal" :data-url="vendor_route('marketing.flash-sales.show', ['flashSale' => $sale->id])" />
        @if ($canManage)
            <x-ui::icon-button icon="edit" size="sm" :label="__('commerce::flash_sales.edit_named', ['title' => $sale->title])" data-remote-modal="vendor-flash-sale-modal" :data-url="vendor_route('marketing.flash-sales.edit', ['flashSale' => $sale->id])" />
            <x-ui::row-actions>
                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('commerce::vendor_promotions.flash_sales.delete_message')" :data-confirm-title="__('commerce::flash_sales.delete_title', ['title' => $sale->title])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('marketing.flash-sales.destroy', ['flashSale' => $sale->id])">{{ __('commerce::promotions.delete') }}</x-ui::dropdown-item>
            </x-ui::row-actions>
        @endif
    @endif
</div>
