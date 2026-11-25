{{--
    Admin / Vendor / Affiliate panel shell (Figma Admin Panel 23:10147): sidebar
    (expanded 264 / collapsed 76 / drawer), sticky topbar, content area, toasts and
    the shared confirm dialog. surface = admin|vendor|affiliate.
--}}
@props(['surface' => 'admin', 'title' => null])
@php
    $shell = app(\App\Shared\Navigation\PanelShell::class)->for($surface);
@endphp
<x-layouts.document
    :surface="$surface"
    :title="$title"
    :entries="['resources/css/'.$surface.'.css', 'resources/js/'.$surface.'.js']"
    body-class="ui-panel bg-canvas text-ink"
>
    <a href="#main" class="sr-only z-50 rounded-control bg-surface px-4 py-2 text-sm font-medium text-ink shadow-pop focus:not-sr-only focus:fixed focus:start-4 focus:top-4">{{ __('ui.skip_to_content') }}</a>

    <x-ui::sidebar
        :sections="$shell['sections']"
        :counts="$shell['counts']"
        :user="$shell['user']"
        :role="$shell['role']"
        :tag="$shell['tag']"
        :home-url="$shell['homeUrl']"
    />

    <div class="ui-shell-main flex min-h-screen flex-col">
        <x-ui::topbar
            :surface="$surface"
            :user="$shell['user']"
            :role="$shell['role']"
            :commands="$shell['commands']"
            :tag="$shell['tag']"
        />
        <main id="main" tabindex="-1" class="flex-1 px-4 py-6 outline-none sm:px-6 xl:px-8 xl:py-8">
            <div {{ $attributes->class(['mx-auto grid w-full max-w-[1600px] gap-6 *:min-w-0']) }}>
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-ui::toasts />
    <x-ui::confirm />
</x-layouts.document>
