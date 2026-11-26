{{-- ADM-063 row actions: edit, delete. --}}
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="edit" size="sm" :label="__('affiliates::admin.promo.edit')" data-remote-modal="affiliate-modal" :data-url="route('admin.affiliates.promo-codes.edit', $code->id)" />
    <x-ui::row-actions>
        <x-ui::dropdown-item icon="trash" tone="danger"
            data-confirm-url="{{ route('admin.affiliates.promo-codes.destroy', $code->id) }}"
            data-confirm-method="DELETE"
            data-confirm-title="{{ __('affiliates::admin.promo.delete_title', ['code' => $code->code]) }}"
            data-confirm="{{ __('affiliates::admin.promo.delete_message') }}"
            data-confirm-label="{{ __('affiliates::admin.promo.delete') }}"
            data-confirm-tone="danger">{{ __('affiliates::admin.promo.delete') }}</x-ui::dropdown-item>
    </x-ui::row-actions>
</div>
