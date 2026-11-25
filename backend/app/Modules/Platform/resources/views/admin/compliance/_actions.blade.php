{{-- Compliance queue › row actions: review, and the store profile in the more menu. --}}
@php
    $store = (string) $profile->getAttribute('store_name');
    $canStore = (bool) auth('admin')->user()?->can('vendors.view');
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.vendors.compliance.show', $profile->id)" :label="__('platform::compliance.actions.review_named', ['store' => $store])" />
    @if ($canStore)
        <x-ui::row-actions :label="__('platform::compliance.actions.more', ['store' => $store])">
            <x-ui::dropdown-item icon="shield" :href="route('admin.vendors.compliance.show', $profile->id)">{{ __('platform::compliance.actions.review') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="store" :href="route('admin.vendors.show', $profile->tenant_id)">{{ __('platform::compliance.actions.store_profile') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
