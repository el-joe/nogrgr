@php($title = $banner->title ?: '#'.$banner->id)
@if (auth('admin')->user()?->can('storefront.banners.manage'))
    <div class="flex items-center justify-end">
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" data-remote-modal="banner-modal" :data-url="route('admin.storefront.banners.edit', $banner)">{{ __('engagement::banners.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('engagement::banners.delete_message')" :data-confirm-title="__('engagement::banners.delete_title', ['title' => $title])" data-confirm-method="DELETE" :data-confirm-url="route('admin.storefront.banners.destroy', $banner)">{{ __('engagement::banners.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    </div>
@endif
