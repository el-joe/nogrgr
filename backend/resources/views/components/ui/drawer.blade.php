{{--
    Drawer (Figma 32:10474): 420 wide from the inline-end side (left in RTL), header /
    scrolling body / sticky footer; full width under 640. Used for "More filters" and
    quick views. Open with data-overlay-open="<id>".
--}}
@props(['id', 'title', 'subtitle' => null, 'width' => 420])
<div id="{{ $id }}" class="ui-overlay" data-overlay data-drawer role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" aria-hidden="true" inert>
    <div class="ui-overlay-scrim"></div>
    <div {{ $attributes->class(['ui-overlay-panel w-full']) }} @style(['max-width: '.(int) $width.'px'])>
        <header class="flex items-start gap-4 border-b border-line px-6 py-5">
            <div class="min-w-0 flex-1">
                <h2 id="{{ $id }}-title" class="text-xl font-semibold leading-7 text-ink">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-0.5 text-[13px] text-ink-secondary">{{ $subtitle }}</p>
                @endif
            </div>
            <x-ui::icon-button icon="x" :label="__('ui.close')" size="sm" data-overlay-close />
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </div>
        @isset($footer)
            <footer class="flex items-center justify-between gap-3 border-t border-line bg-subtle px-6 py-4">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
