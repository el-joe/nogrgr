{{-- Central marketing website (Figma page 12:41054). --}}
@props(['title' => null])
<x-layouts.document
    surface="central"
    :title="$title"
    :entries="['resources/css/central.css', 'resources/js/central.js']"
    body-class="bg-surface text-ink"
>
    @isset($head)
        <x-slot:head>{{ $head }}</x-slot:head>
    @endisset
    {{ $slot }}
</x-layouts.document>
