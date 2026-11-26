{{-- Tenant storefront. family = ecommet|elora|souqify (theme registry decides). --}}
@props(['family' => 'ecommet', 'title' => null, 'fullTitle' => null])
<x-layouts.document
    surface="storefront"
    :title="$title"
    :full-title="$fullTitle"
    :entries="['resources/css/storefront-'.$family.'.css', 'resources/js/storefront-'.$family.'.js']"
    body-class="bg-surface text-ink"
    data-theme-family="{{ $family }}"
    data-motion-root
>
    <x-slot:head>{{ $head ?? '' }}</x-slot:head>
    {{ $slot }}
</x-layouts.document>
