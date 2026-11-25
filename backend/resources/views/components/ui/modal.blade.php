{{--
    Modal (Figma 32:10353) → Bottom sheet under 640px (32:10414).
    size: sm 400 | md 560 | lg 720 | xl 960. Header 20×24 with close, body padding 24,
    footer on bg/subtle with actions on the inline-end. Esc / scrim close it (a dirty
    form asks first), focus is trapped. Open with data-overlay-open="<id>".
    close-on-success closes it after its data-ajax form succeeds.
--}}
@props(['id', 'title', 'subtitle' => null, 'size' => 'md', 'sheet' => true, 'closeOnSuccess' => false, 'static' => false])
@php
    $width = ['sm' => 'max-w-[400px]', 'md' => 'max-w-[560px]', 'lg' => 'max-w-[720px]', 'xl' => 'max-w-[960px]'][$size] ?? 'max-w-[560px]';
@endphp
<div
    id="{{ $id }}"
    class="ui-overlay"
    data-overlay
    @if ($sheet) data-sheet @endif
    @if ($closeOnSuccess) data-close-on-success @endif
    @if ($static) data-static @endif
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $id }}-title"
    @if ($subtitle) aria-describedby="{{ $id }}-subtitle" @endif
    aria-hidden="true"
    inert
>
    <div class="ui-overlay-scrim"></div>
    <div {{ $attributes->class(['ui-overlay-panel', $width]) }}>
        <div class="ui-sheet-grabber mx-auto mt-2 h-1 w-10 rounded-full bg-line-strong" data-sheet-handle aria-hidden="true"></div>
        <header class="flex items-start gap-4 border-b border-line px-6 py-5" data-sheet-handle>
            <div class="min-w-0 flex-1">
                <h2 id="{{ $id }}-title" class="text-xl font-semibold leading-7 text-ink">{{ $title }}</h2>
                @if ($subtitle)
                    <p id="{{ $id }}-subtitle" class="mt-0.5 text-[13px] text-ink-secondary">{{ $subtitle }}</p>
                @endif
            </div>
            <x-ui::icon-button icon="x" :label="__('ui.close')" size="sm" data-overlay-close />
        </header>
        <div class="min-h-0 flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </div>
        @isset($footer)
            <footer class="ui-modal-footer flex items-center justify-end gap-3 rounded-b-card border-t border-line bg-subtle px-6 py-4">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
