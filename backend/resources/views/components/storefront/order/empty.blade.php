{{-- Account empty state card (Figma 100:102142): icon, title, text, optional CTA. --}}
@props(['icon' => 'cart', 'title', 'text' => null, 'action' => null, 'href' => null])
<div {{ $attributes->class(['sf-card flex flex-col items-center gap-2.5 p-6 text-center']) }}>
    <x-ui::icon :name="$icon" :size="32" class="text-sf-ink" />
    <p class="text-lg font-semibold leading-[26px] text-sf-ink">{{ $title }}</p>
    @if ($text)<p class="text-sm leading-5 text-sf-muted">{{ $text }}</p>@endif
    @if ($action && $href)
        <a href="{{ $href }}" class="sf-btn sf-btn-cta mt-0.5 px-6">{{ $action }}</a>
    @endif
    {{ $slot }}
</div>
