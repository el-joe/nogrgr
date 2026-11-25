{{-- Inline banner. tone: danger|warning|success. --}}
@props(['tone' => 'danger'])
@php($icon = ['danger' => 'x-circle', 'warning' => 'alert-triangle', 'success' => 'check-circle'][$tone] ?? 'info')
<div {{ $attributes->class(['sf-alert', 'sf-alert-'.$tone]) }} role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    <x-ui::icon :name="$icon" :size="18" class="mt-px shrink-0" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
