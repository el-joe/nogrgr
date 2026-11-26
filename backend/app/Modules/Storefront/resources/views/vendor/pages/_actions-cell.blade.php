{{-- Row actions of VEN-033: view on the store (published pages), edit, delete (custom pages only). --}}
@php
    $tenant = tenant();
    $title = (string) ($page->title ?: $page->slug);
    $storeUrl = $tenant instanceof \App\Modules\Platform\Models\Tenant ? \App\Modules\Platform\Services\StoreSettingsService::storeUrl($tenant).'/pages/'.$page->slug : null;
    $system = $page->type === \App\Modules\Storefront\Enums\StorePageType::System;
@endphp
<div class="flex items-center justify-end gap-1">
    @if ($storeUrl && $page->is_active)
        <x-ui::icon-button icon="eye" size="sm" :label="__('content::store_pages.view_named', ['title' => $title])" :href="$storeUrl" target="_blank" rel="noopener" />
    @endif
    @if ($canManage)
        <x-ui::icon-button icon="edit" size="sm" :label="__('content::store_pages.edit')" :href="vendor_route('store.pages.edit', ['page' => $page->id])" />
        <x-ui::row-actions>
            <x-ui::dropdown-item icon="edit" :href="vendor_route('store.pages.edit', ['page' => $page->id])">{{ __('content::store_pages.edit') }}</x-ui::dropdown-item>
            @if ($system)
                <x-ui::dropdown-item icon="lock" disabled :title="__('content::store_pages.system_locked')">{{ __('content::store_pages.system_locked') }}</x-ui::dropdown-item>
            @else
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('content::store_pages.delete_message')" :data-confirm-title="__('content::store_pages.delete_title', ['title' => $title])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('store.pages.destroy', ['page' => $page->id])">{{ __('content::store_pages.delete') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
