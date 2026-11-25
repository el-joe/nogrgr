{{--
    Login / password pages of the panels (and storefront customer auth).
    Panels (admin / vendor / affiliate) use the split Figma layout (Login 55:20387):
    brand gradient panel + sign-in card; the storefront keeps its theme's centered card.
--}}
@props(['surface' => 'admin', 'title' => null])
@php
    $entry = $surface === 'storefront' ? 'storefront-'.\App\Modules\Storefront\Support\StorefrontTheme::family() : $surface;
    $isPanel = in_array($surface, ['admin', 'vendor', 'affiliate', 'owner'], true);
@endphp
<x-layouts.document
    :surface="$surface"
    :title="$title"
    :entries="['resources/css/'.$entry.'.css', 'resources/js/'.$entry.'.js']"
    :body-class="$isPanel ? 'ui-auth bg-canvas text-ink' : 'bg-surface text-ink grid place-items-center'"
>
    @if ($isPanel)
        {{ $slot }}
        <x-ui::toasts />
    @else
        <main class="w-full max-w-md p-6">
            {{ $slot }}
        </main>
    @endif
</x-layouts.document>
