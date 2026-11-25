{{-- Card: radius 16, padding 20, Shadow/XS. Optional title / subtitle / actions header. --}}
@props(['title' => null, 'subtitle' => null, 'padding' => true, 'id' => null])
<section @if ($id) id="{{ $id }}" @endif {{ $attributes->class(['rounded-card border border-line bg-surface shadow-card', 'p-5' => $padding]) }} @if ($title && $id) aria-labelledby="{{ $id }}-title" @endif>
    @if ($title || isset($actions))
        <header @class(['mb-4 flex items-start justify-between gap-3', 'px-5 pt-5' => ! $padding])>
            <div class="min-w-0">
                @if ($title)
                    <h2 @if ($id) id="{{ $id }}-title" @endif class="text-base font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="text-xs text-ink-secondary">{{ $subtitle }}</p>
                @endif
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif
    {{ $slot }}
</section>
