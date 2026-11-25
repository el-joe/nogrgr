{{--
    Storefront modal → bottom sheet under 640px with drag-to-dismiss (motion sheet.js).
    Open with data-sheet-open="<id>"; [data-sheet-close] closes it.
--}}
@props(['id', 'title', 'size' => 'md'])
@php($width = ['sm' => 'sm:max-w-[400px]', 'md' => 'sm:max-w-[560px]', 'lg' => 'sm:max-w-[720px]'][$size] ?? 'sm:max-w-[560px]')
<div class="mo-sheet" id="{{ $id }}" data-sheet role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" hidden {{ $attributes->except('class') }}>
    <div class="mo-sheet-scrim" data-sheet-close></div>
    <div {{ $attributes->only('class')->class(['mo-sheet-panel', $width]) }}>
        <div data-sheet-handle class="px-5 pt-1 sm:px-6 sm:pt-5">
            <span class="mo-sheet-grabber" aria-hidden="true"></span>
            <div class="flex items-start justify-between gap-4 pb-4">
                <h2 id="{{ $id }}-title" class="text-xl font-semibold text-sf-ink" data-modal-title>{{ $title }}</h2>
                <button type="button" data-sheet-close class="grid size-8 place-items-center rounded-full text-sf-ink hover:bg-black/5" aria-label="{{ __st('shell.close') }}"><x-ui::icon name="x" :size="20" /></button>
            </div>
        </div>
        <div class="px-5 pb-5 sm:px-6 sm:pb-6">
            {{ $slot }}
        </div>
    </div>
</div>
