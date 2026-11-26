{{-- Store banners: edit / delete. Platform banners are read-only (show / hide is the switch). --}}
@php($title = $row->title())
<div class="flex items-center justify-end">
    @if ($canManage && ! $row->isCentral())
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" data-remote-modal="vendor-banner-modal" :data-url="vendor_route('store.banners.edit', ['banner' => $row->banner->id])">{{ __('engagement::banners.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('engagement::banners.delete_message')" :data-confirm-title="__('engagement::banners.delete_title', ['title' => $title])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('store.banners.destroy', ['banner' => $row->banner->id])">{{ __('engagement::banners.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
