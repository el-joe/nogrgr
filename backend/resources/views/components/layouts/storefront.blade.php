{{-- Tenant storefront. family = ecommet|elora|souqify (theme registry decides). --}}
@props(['family' => 'ecommet', 'title' => null])
<x-layouts.document
    surface="storefront"
    :title="$title"
    :entries="['resources/css/storefront-'.$family.'.css', 'resources/js/storefront-'.$family.'.js']"
    body-class="bg-surface text-ink"
    data-theme-family="{{ $family }}"
    data-motion-root
>
    {{ $slot }}
</x-layouts.document>
