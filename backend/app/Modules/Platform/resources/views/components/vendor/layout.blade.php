{{--
    Vendor (store) panel shell — Tenant Panel Layout (Figma 12:25829 side menu, topbar
    of Dashboard 12:11267) with the Vendor Panel (new screens) additions: language,
    dark / light, notifications bell, store link, impersonation banner (82:101033).
    Nav = config/navigation/vendor.php filtered by permission (PanelShell); the "setup"
    badge counts open setup checklist steps.
    <x-vendor::layout :title="…" page="vendor-onboarding">…</x-vendor::layout>
--}}
@props(['title' => null, 'page' => null])
@php
    $shell = app(\App\Shared\Navigation\PanelShell::class)->for('vendor');
    $tenant = tenant();
    $store = $tenant instanceof \App\Modules\Platform\Models\Tenant ? $tenant : null;
    $storeUrl = $store ? \App\Modules\Platform\Services\StoreSettingsService::storeUrl($store) : null;
    $logoUrl = $store ? app(\App\Modules\Platform\Services\StoreSettingsService::class)->logoUrl() : null;
    $impersonation = app(\App\Modules\Identity\Services\VendorImpersonationService::class)->current(request());
@endphp
<x-layouts.document
    surface="vendor"
    :title="$title"
    :entries="['resources/css/vendor.css', 'resources/js/vendor.js']"
    body-class="ui-panel vnd-shell bg-canvas text-ink"
>
    <a href="#main" class="sr-only z-50 rounded-control bg-surface px-4 py-2 text-sm font-medium text-ink shadow-pop focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('ui.skip_to_content') }}</a>

    <x-vendor::sidebar
        :sections="$shell['sections']"
        :counts="$shell['counts']"
        :home-url="$shell['homeUrl']"
        :store-name="$store?->name"
        :logo-url="$logoUrl"
    />

    <div class="ui-shell-main flex min-h-screen flex-col">
        <x-vendor::topbar :shell="$shell" :store-url="$storeUrl" />

        @if ($impersonation)
            <x-vendor::impersonation-banner :impersonation="$impersonation" :store-name="$store?->name" />
        @endif

        <main id="main" tabindex="-1" class="flex-1 px-4 py-6 outline-none sm:px-6 xl:px-6 xl:py-8" @if ($page) data-page="{{ $page }}" @endif>
            <div {{ $attributes->class(['mx-auto grid w-full max-w-[1156px] gap-8 *:min-w-0']) }}>
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-ui::toasts />
    <x-ui::confirm />
</x-layouts.document>
