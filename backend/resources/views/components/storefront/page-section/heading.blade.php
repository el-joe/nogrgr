{{-- Heading of a store-page section (VEN-032): title + optional subtitle, theme sf-* tokens. --}}
@props(['title' => null, 'subtitle' => null, 'id' => null])
@if ($title || $subtitle)
    <div {{ $attributes->class(['grid gap-1']) }} data-reveal="fade-up">
        @if ($title)<h2 @if ($id) id="{{ $id }}" @endif class="text-xl font-semibold leading-8 text-sf-ink md:text-2xl">{{ $title }}</h2>@endif
        @if ($subtitle)<p class="text-sm leading-5 text-sf-muted">{{ $subtitle }}</p>@endif
    </div>
@endif
