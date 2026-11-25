{{-- Central marketing website (Figma page 12:41054). --}}
@props(['title' => null])
<x-layouts.document
    surface="central"
    :title="$title"
    :entries="['resources/css/central.css', 'resources/js/central.js']"
    body-class="bg-surface text-ink"
>
    {{ $slot }}
</x-layouts.document>
