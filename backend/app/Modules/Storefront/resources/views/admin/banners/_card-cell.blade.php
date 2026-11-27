{{-- Banner row card (Figma 78:76059): drag handle, position, image 86×52, title, link · schedule · countries. --}}
@php
    $canManage = (bool) auth('admin')->user()?->can('storefront.banners.manage');
    $locale = app()->getLocale();
    $format = fn ($date) => $date->locale($locale)->isoFormat('DD MMM');
    $schedule = match (true) {
        $banner->starts_at !== null && $banner->ends_at !== null => __('engagement::banners.range', ['from' => $format($banner->starts_at), 'to' => $format($banner->ends_at)]),
        $banner->starts_at !== null => __('engagement::banners.from', ['date' => $format($banner->starts_at)]),
        $banner->ends_at !== null => __('engagement::banners.until', ['date' => $format($banner->ends_at)]),
        default => __('engagement::banners.always'),
    };
    $countryLabel = $banner->countries->isEmpty() ? __('engagement::banners.countries_all') : $banner->countries->pluck('iso2')->implode(', ');
    $title = $banner->title ?: '#'.$banner->id;
@endphp
<div class="flex min-w-0 items-center gap-3">
    @if ($canManage)
        <button type="button" class="grid size-8 shrink-0 cursor-grab place-items-center rounded-control text-ink-muted hover:bg-subtle hover:text-ink active:cursor-grabbing" data-drag-handle aria-label="{{ __('engagement::banners.drag_named', ['name' => $title]) }}" title="{{ __('engagement::banners.drag') }}">
            <x-ui::icon name="menu" :size="16" />
        </button>
    @endif
    <span class="w-5 shrink-0 text-center text-xs text-ink-muted" dir="ltr" data-sort-position></span>
    <img src="{{ $banner->imageUrl() }}" alt="{{ $title }}" width="86" height="52" loading="lazy" class="h-[52px] w-[86px] shrink-0 rounded-control bg-subtle object-contain">
    <div class="min-w-0">
        <p class="truncate text-sm font-medium text-ink">{{ $title }}</p>
        <p class="mt-0.5 truncate text-xs text-ink-muted">
            <span dir="auto">{{ $banner->link_url ? __('engagement::banners.link', ['url' => $banner->link_url]) : __('engagement::banners.no_link') }}</span>
            · {{ $schedule }} · <span dir="ltr">{{ $countryLabel }}</span>
            @if ($banner->store_scope === \App\Modules\Commerce\Enums\PromotionScope::SelectedStores)
                · {{ __('engagement::banners.stores_selected', ['count' => (int) $banner->getAttribute('tenants_count')]) }}
            @endif
        </p>
    </div>
</div>
