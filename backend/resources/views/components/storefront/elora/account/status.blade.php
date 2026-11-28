{{-- Elora order status pill (Figma 1:18739 desktop, m 1:23902 phone): icon by tone. --}}
@props(['tone' => 'info'])
@php($icon = ['success' => 'elo-status-delivered', 'info' => 'elo-status-shipped', 'warning' => 'elo-status-processing', 'danger' => 'x'][$tone] ?? 'elo-status-shipped')
<span {{ $attributes->class(['eac-status']) }} data-tone="{{ $tone }}"><x-ui::icon :name="$icon" :size="20" />{{ $slot }}</span>
