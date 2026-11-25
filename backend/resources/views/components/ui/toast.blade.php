{{--
    Toast (Figma 31:10312) rendered on the server (session flashes, gallery). JS toasts
    come from notify.js with the same markup. Success/Info 5s, Warning 8s, Error stays.
--}}
@props(['tone' => 'info', 'title', 'message' => null, 'static' => false])
@php
    $icons = ['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'alert-triangle', 'info' => 'info'];
    $accent = ['success' => 'var(--color-success)', 'error' => 'var(--color-danger)', 'warning' => 'var(--color-warning)', 'info' => 'var(--color-info)'][$tone] ?? 'var(--color-info)';
    $timeout = ['success' => 5000, 'info' => 5000, 'warning' => 8000, 'error' => 0][$tone] ?? 5000;
@endphp
<div
    {{ $attributes->class(['ui-toast']) }}
    data-tone="{{ $tone }}"
    @unless ($static) data-toast-timeout="{{ $timeout }}" @endunless
    @style(['--toast-accent: '.$accent])
    role="{{ $tone === 'error' ? 'alert' : 'status' }}"
>
    <x-ui::icon :name="$icons[$tone] ?? 'info'" :size="20" class="mt-px" :style="'color: '.$accent" />
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-ink">{{ $title }}</p>
        @if ($message)
            <p class="mt-0.5 text-[13px] leading-[18px] text-ink-secondary">{{ $message }}</p>
        @endif
    </div>
    <button type="button" data-toast-close class="grid size-6 shrink-0 place-items-center rounded-chip text-ink-muted hover:bg-subtle hover:text-ink" aria-label="{{ __('ui.close') }}">
        <x-ui::icon name="x" :size="16" />
    </button>
</div>
