{{--
    One theme card of the vendor themes page (VEN-030, Figma 85:104756, decision #119):
    preview image (object-contain), name, description, chips (Default, "Active in: …",
    "Available in: …" when the theme is limited to some countries), actions (Activate as
    store default, "Use in countries" modal, Preview in a new tab).
    $use = ['default' => bool, 'countries' => [names]]; $allowedCountries = id => name.
--}}
@php
    $isDefault = $use['default'];
    $restricted = $theme->countries->isNotEmpty() && count($allowedCountries) < count($countries);
@endphp
<article @class([
    'vnd-theme-card group flex flex-col overflow-hidden rounded-card border bg-surface shadow-card transition duration-200 ease-out hover:-translate-y-0.5 hover:shadow-md motion-reduce:transition-none motion-reduce:hover:translate-y-0',
    'border-primary ring-1 ring-primary' => $isDefault,
    'border-line' => ! $isDefault,
]) data-theme-id="{{ $theme->id }}" @if ($isDefault) aria-current="true" @endif>
    <div class="relative aspect-[16/10] overflow-hidden bg-media">
        @if ($theme->preview_url)
            <img src="{{ $theme->preview_url }}" alt="{{ __('content::vendor_themes.preview_of', ['theme' => $theme->name]) }}" loading="lazy" decoding="async" class="size-full object-contain transition-transform duration-300 ease-out group-hover:scale-[1.02] motion-reduce:transition-none motion-reduce:group-hover:scale-100">
        @else
            <span class="grid size-full place-items-center text-ink-muted"><x-ui::icon name="palette" :size="28" /></span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-3 p-4">
        <div class="grid gap-1">
            <div class="flex items-center justify-between gap-2">
                <h3 class="truncate text-sm font-semibold text-ink">{{ $theme->name }}</h3>
                @if ($isDefault)
                    <x-ui::badge tone="brand">{{ __('content::vendor_themes.default_chip') }}</x-ui::badge>
                @endif
            </div>
            @if ($theme->description)
                <p class="line-clamp-2 text-xs text-ink-secondary">{{ $theme->description }}</p>
            @endif
        </div>
        @if ($use['countries'] !== [] || $restricted)
            <div class="flex flex-wrap gap-1.5">
                @if ($use['countries'] !== [])
                    <x-ui::badge tone="success" class="max-w-full" title="{{ implode(', ', $use['countries']) }}"><span class="truncate">{{ __('content::vendor_themes.active_in', ['countries' => implode(', ', $use['countries'])]) }}</span></x-ui::badge>
                @endif
                @if ($restricted)
                    <x-ui::badge tone="info" variant="outline" class="max-w-full" title="{{ implode(', ', $allowedCountries) }}"><span class="truncate">{{ __('content::vendor_themes.available_in', ['countries' => implode(', ', $allowedCountries)]) }}</span></x-ui::badge>
                @endif
            </div>
        @endif
        <div class="mt-auto flex flex-wrap items-center gap-2 pt-1">
            @if ($canManage && ! $isDefault)
                <form method="POST" action="{{ vendor_route('store.themes.activate') }}" data-ajax class="contents">
                    @csrf
                    <input type="hidden" name="theme" value="{{ $theme->id }}">
                    <input type="hidden" name="default" value="1">
                    <x-ui::button type="submit" size="sm">{{ __('content::vendor_themes.activate_default') }}</x-ui::button>
                </form>
            @endif
            @if ($canManage && $allowedCountries !== [])
                <x-ui::button size="sm" variant="secondary" data-remote-modal="vendor-theme-modal" :data-url="vendor_route('store.themes.countries', ['theme' => $theme->id])">{{ __('content::vendor_themes.use_in_countries') }}</x-ui::button>
            @endif
            <x-ui::icon-button size="sm" variant="ghost" icon="eye" :href="vendor_route('store.themes.preview', ['theme' => $theme->id])" target="_blank" rel="noopener" :label="__('content::vendor_themes.preview_of', ['theme' => $theme->name])" class="ms-auto" />
        </div>
    </div>
</article>
