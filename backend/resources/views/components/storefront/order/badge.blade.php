{{-- Status pill (Figma 100:102051): tone success | info | warning | danger. --}}
@props(['tone' => 'info'])
@php
    $tones = [
        'success' => 'bg-sf-success-bg text-sf-success',
        'info' => 'bg-sf-info-bg text-sf-info',
        'warning' => 'bg-sf-warning-bg text-sf-warning',
        'danger' => 'bg-sf-danger-bg text-sf-danger',
    ];
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center whitespace-nowrap rounded-full px-2.5 py-[3px] text-xs font-medium leading-4', $tones[$tone] ?? $tones['info']]) }}>{{ $slot }}</span>
