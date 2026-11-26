{{-- Status badge (active / scheduled / ended / off) + on / off switch (PATCH). --}}
@php($status = $banner->status())
<div class="flex items-center justify-end gap-3">
    <x-ui::status :status="$status->badge()">{{ $status->label() }}</x-ui::status>
    @if (auth('admin')->user()?->can('storefront.banners.manage'))
        <x-ui::switch :name="'is_active_'.$banner->id" :id="'banner-active-'.$banner->id" :checked="$banner->is_active" :url="route('admin.storefront.banners.toggle', $banner)" field="is_active" :aria-label="__('engagement::banners.active_named', ['title' => $banner->title ?: '#'.$banner->id])" />
    @endif
</div>
