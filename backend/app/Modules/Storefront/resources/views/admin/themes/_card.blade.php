{{-- One catalogue theme card (ADM-086). --}}
@php
    $countries = $theme->countries;
    $countryLabel = $countries->isEmpty() ? __('content::admin_themes.all_countries') : ($countries->count() <= 3 ? $countries->pluck('name')->implode(', ') : trans_choice('content::admin_themes.countries_count', $countries->count(), ['count' => $countries->count()]));
    $name = (string) $theme->name;
@endphp
<li class="flex min-w-0 flex-col overflow-hidden rounded-card border border-line bg-surface shadow-card" data-theme-card="{{ $theme->id }}">
    <div class="relative aspect-[3/4] overflow-hidden bg-media">
        @if ($theme->preview_url)
            <img src="{{ $theme->preview_url }}" alt="{{ $name }}" loading="lazy" decoding="async" class="absolute inset-0 size-full object-contain">
        @else
            <div class="grid size-full place-items-center text-ink-muted"><span class="grid justify-items-center gap-2 text-xs"><x-ui::icon name="image" :size="32" />{{ __('content::admin_themes.no_preview') }}</span></div>
        @endif
        @if ($theme->is_default)
            <span class="absolute start-3 top-3"><x-ui::badge tone="brand" variant="solid">{{ __('content::admin_themes.default') }}</x-ui::badge></span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-3 p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="truncate text-base font-semibold text-ink" dir="auto">{{ $name }}</h2>
                <p class="mt-1 line-clamp-2 text-xs text-ink-secondary" dir="auto">{{ $theme->description }}</p>
            </div>
            @if ($canManage)
                <x-ui::row-actions :label="__('content::admin_themes.actions', ['theme' => $name])">
                    <x-ui::dropdown-item icon="edit" data-remote-modal="theme-modal" :data-url="route('admin.storefront.themes.edit', $theme)">{{ __('content::admin_themes.edit') }}</x-ui::dropdown-item>
                    <x-ui::dropdown-item icon="globe" data-remote-modal="theme-countries-modal" :data-url="route('admin.storefront.themes.countries', $theme)">{{ __('content::admin_themes.countries') }}</x-ui::dropdown-item>
                    <x-ui::dropdown-item icon="layers" :href="route('admin.storefront.home-sections.index', ['theme' => $theme->id])">{{ __('content::admin_themes.home_sections') }}</x-ui::dropdown-item>
                    @if ($canParts)
                        <x-ui::dropdown-item icon="grid" :href="route('admin.storefront.template-parts.index', ['theme' => $theme->id])">{{ __('content::admin_themes.template_parts') }}</x-ui::dropdown-item>
                    @endif
                    <x-ui::dropdown-divider />
                    @if ($theme->is_active && ! $theme->is_default)
                        <x-ui::dropdown-item icon="star" :data-confirm="__('content::admin_themes.confirm.default')" :data-confirm-title="__('content::admin_themes.confirm.default_title', ['theme' => $name])" data-confirm-tone="primary" :data-confirm-label="__('content::admin_themes.make_default')" data-confirm-method="POST" :data-confirm-url="route('admin.storefront.themes.default', $theme)">{{ __('content::admin_themes.make_default') }}</x-ui::dropdown-item>
                    @endif
                    @if ($theme->is_active)
                        @unless ($theme->is_default)
                            <x-ui::dropdown-item icon="x-circle" tone="danger" :data-confirm="__('content::admin_themes.confirm.deactivate')" :data-confirm-title="__('content::admin_themes.confirm.deactivate_title', ['theme' => $name])" :data-confirm-label="__('content::admin_themes.deactivate')" data-confirm-method="POST" :data-confirm-url="route('admin.storefront.themes.deactivate', $theme)">{{ __('content::admin_themes.deactivate') }}</x-ui::dropdown-item>
                        @endunless
                    @else
                        <x-ui::dropdown-item icon="check-circle" :data-confirm="__('content::admin_themes.confirm.activate')" :data-confirm-title="__('content::admin_themes.confirm.activate_title', ['theme' => $name])" data-confirm-tone="primary" :data-confirm-label="__('content::admin_themes.activate')" data-confirm-method="POST" :data-confirm-url="route('admin.storefront.themes.activate', $theme)">{{ __('content::admin_themes.activate') }}</x-ui::dropdown-item>
                    @endif
                </x-ui::row-actions>
            @endif
        </div>
        <div class="mt-auto flex flex-wrap items-center gap-2">
            <x-ui::status :status="$theme->is_active ? 'active' : 'inactive'">{{ $theme->is_active ? __('content::admin_themes.active') : __('content::admin_themes.inactive') }}</x-ui::status>
            <x-ui::badge tone="neutral" variant="outline"><x-ui::icon name="globe" :size="12" /><span class="max-w-40 truncate">{{ $countryLabel }}</span></x-ui::badge>
            <span class="text-xs text-ink-muted">{{ trans_choice('content::admin_themes.parts_count', (int) $theme->parts_count, ['count' => (int) $theme->parts_count]) }}</span>
        </div>
    </div>
</li>
