{{--
    The store's published custom Twig theme as a selectable entry of the theme list
    (#119 / #121 seam, ThemePageService::customTheme): "Use custom theme" / "Use built-in
    theme" flips the custom theme switch (store.custom-themes.active, back to this page),
    Preview opens the signed storefront preview of the live version.
    @var array{name: string, version: string, version_id: int, active: bool, published_at: string|null, approved_count: int} $customTheme
--}}
<x-ui::card id="vendor-custom-theme" :title="__('content::vendor_themes.custom.title')" :subtitle="__('content::vendor_themes.custom.subtitle')" data-custom-theme-entry data-active="{{ $customTheme['active'] ? 'true' : 'false' }}">
    <div class="flex flex-wrap items-center gap-4">
        <span class="grid size-14 shrink-0 place-items-center rounded-[12px] bg-primary-subtle text-primary" aria-hidden="true"><x-ui::icon name="palette" :size="26" /></span>
        <div class="grid min-w-0 flex-1 gap-1">
            <p class="flex flex-wrap items-center gap-2">
                <span class="truncate text-base font-semibold text-ink">{{ $customTheme['name'] }}</span>
                <x-ui::badge tone="brand">{{ __('content::vendor_themes.custom.badge') }}</x-ui::badge>
                <x-ui::badge :tone="$customTheme['active'] ? 'success' : 'neutral'">{{ $customTheme['active'] ? __('content::vendor_themes.custom.on') : __('content::vendor_themes.custom.off') }}</x-ui::badge>
            </p>
            <p class="text-sm text-ink-secondary">
                <span dir="ltr">{{ __('content::vendor_themes.custom.version', ['version' => $customTheme['version']]) }}</span>
                @if ($customTheme['published_at'])
                    · {{ __('content::vendor_themes.custom.published', ['date' => $customTheme['published_at']]) }}
                @endif
                · {{ trans_choice('content::vendor_themes.custom.versions', $customTheme['approved_count'], ['count' => $customTheme['approved_count']]) }}
            </p>
        </div>
        <div class="flex flex-wrap gap-3">
            <x-ui::button variant="ghost" icon="eye" :href="vendor_route('store.custom-themes.preview', ['version' => $customTheme['version_id']])" target="_blank" rel="noopener">{{ __('content::vendor_themes.preview') }}</x-ui::button>
            <x-ui::button variant="secondary" icon="settings" :href="vendor_route('store.custom-themes.index')">{{ __('content::vendor_themes.custom.manage') }}</x-ui::button>
            @if ($canManageCustom)
                @if ($customTheme['active'])
                    <x-ui::button variant="secondary" icon="refresh" :href="vendor_route('store.custom-themes.active', ['active' => 0, 'return' => 'themes'])" data-confirm="{{ __('content::vendor_themes.custom.stop_confirm') }}" data-confirm-title="{{ __('content::vendor_themes.custom.stop_confirm_title') }}" data-confirm-tone="warning" data-confirm-method="PATCH" data-custom-theme-toggle>{{ __('content::vendor_themes.custom.stop') }}</x-ui::button>
                @else
                    <x-ui::button icon="check" :href="vendor_route('store.custom-themes.active', ['active' => 1, 'return' => 'themes'])" data-confirm="{{ __('content::vendor_themes.custom.use_confirm', ['theme' => $customTheme['name']]) }}" data-confirm-title="{{ __('content::vendor_themes.custom.use_confirm_title') }}" data-confirm-tone="primary" data-confirm-method="PATCH" data-custom-theme-toggle>{{ __('content::vendor_themes.custom.use') }}</x-ui::button>
                @endif
            @endif
        </div>
    </div>
</x-ui::card>
