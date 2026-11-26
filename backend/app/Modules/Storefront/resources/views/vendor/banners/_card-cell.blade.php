{{-- Banner row card (Figma 85:108271): drag handle (own banners), position, image 86×52, title, origin · link · schedule. --}}
@php
    $banner = $row->banner;
    $locale = app()->getLocale();
    $format = fn ($date) => $date->locale($locale)->isoFormat('DD MMM');
    $schedule = match (true) {
        $banner->starts_at !== null && $banner->ends_at !== null => __('engagement::banners.range', ['from' => $format($banner->starts_at), 'to' => $format($banner->ends_at)]),
        $banner->starts_at !== null => __('engagement::banners.from', ['date' => $format($banner->starts_at)]),
        $banner->ends_at !== null => __('engagement::banners.until', ['date' => $format($banner->ends_at)]),
        default => null,
    };
    $title = $row->title();
@endphp
<div class="flex min-w-0 items-center gap-3">
    @if ($canManage && ! $row->isCentral())
        <button type="button" class="grid size-8 shrink-0 cursor-grab place-items-center rounded-control text-ink-muted hover:bg-subtle hover:text-ink active:cursor-grabbing" data-drag-handle aria-label="{{ __('engagement::banners.drag') }}" title="{{ __('engagement::banners.drag') }}">
            <x-ui::icon name="menu" :size="16" />
        </button>
    @else
        <span class="grid size-8 shrink-0 place-items-center text-ink-muted" aria-hidden="true"><x-ui::icon name="menu" :size="16" class="opacity-40" /></span>
    @endif
    <span class="w-5 shrink-0 text-center text-xs text-ink-muted" dir="ltr" data-sort-position></span>
    <img src="{{ $banner->imageUrl() }}" alt="{{ $title }}" width="86" height="52" loading="lazy" @class(['h-[52px] w-[86px] shrink-0 rounded-control bg-subtle object-contain', 'opacity-50' => $row->hidden])>
    <div class="min-w-0">
        <p class="truncate text-sm font-medium text-ink">{{ $title }}</p>
        <p class="mt-0.5 truncate text-xs text-ink-muted">
            @if ($row->isCentral())
                {{ __('engagement::vendor_banners.platform_banner', ['app' => config('app.name')]) }}
            @else
                {{ __('engagement::vendor_banners.your_banner') }}@if ($banner->link_url) · <span dir="ltr">{{ $banner->link_url }}</span>@endif
                @if ($schedule) · {{ $schedule }}@endif
            @endif
        </p>
    </div>
</div>
