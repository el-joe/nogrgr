<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('commerce::flash_sales.view_named', ['title' => $sale->title])" data-remote-modal="flash-sale-view-modal" :data-url="route('admin.promotions.flash-sales.show', $sale)" />
    @if (auth('admin')->user()?->can('flash-sales.manage'))
        <x-ui::icon-button icon="edit" size="sm" :label="__('commerce::flash_sales.edit_named', ['title' => $sale->title])" data-remote-modal="flash-sale-modal" :data-url="route('admin.promotions.flash-sales.edit', $sale)" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('commerce::flash_sales.delete_message')" :data-confirm-title="__('commerce::flash_sales.delete_title', ['title' => $sale->title])" data-confirm-method="DELETE" :data-confirm-url="route('admin.promotions.flash-sales.destroy', $sale)">{{ __('commerce::promotions.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
