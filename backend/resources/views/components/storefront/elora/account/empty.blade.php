{{-- Elora account empty state (Figma 1:19347 / m 1:23542): round art, title, text, dark pill CTA. --}}
@props(['icon' => 'box', 'title', 'text' => null, 'action' => null, 'href' => null])
<div {{ $attributes->class(['elo-empty eac-empty']) }}>
    <span class="elo-empty-icon"><x-ui::icon :name="$icon" :size="40" /></span>
    <h2>{{ $title }}</h2>
    @if ($text)<p>{{ $text }}</p>@endif
    @if ($action && $href)
        <a href="{{ $href }}" class="elo-btn elo-btn-ink mo-press mt-2 px-6">{{ $action }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a>
    @endif
</div>
