{{-- Platform banner: "Admin" (+ "Hidden") badge and show / hide switch; store banner: status badge + on / off switch. --}}
@php($title = $row->title())
<div class="flex items-center justify-end gap-3">
    @if ($row->isCentral())
        @if ($row->hidden)
            <x-ui::status status="inactive">{{ __('engagement::vendor_banners.hidden_badge') }}</x-ui::status>
        @endif
        <x-ui::status status="neutral">{{ __('engagement::vendor_banners.admin_badge') }}</x-ui::status>
        @if ($canManage)
            <x-ui::switch :name="'visible_'.$row->banner->id" :id="'central-banner-visible-'.$row->banner->id" :checked="! $row->hidden" :url="vendor_route('store.banners.visibility', ['banner' => $row->banner->id])" field="visible" :aria-label="__('engagement::vendor_banners.show_named', ['title' => $title])" />
        @endif
    @else
        @php($status = $row->banner->status())
        <x-ui::status :status="$status->badge()">{{ $status->label() }}</x-ui::status>
        @if ($canManage)
            <x-ui::switch :name="'is_active_'.$row->banner->id" :id="'store-banner-active-'.$row->banner->id" :checked="$row->banner->is_active" :url="vendor_route('store.banners.toggle', ['banner' => $row->banner->id])" field="is_active" :aria-label="__('engagement::banners.active_named', ['title' => $title])" />
        @endif
    @endif
</div>
