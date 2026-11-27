{{-- Store maintenance (SF-ALL-002, Figma 114:108783 / m 114:108812): app down (503) on a store host; "expected back" from Retry-After when known. --}}
@extends('storefront::themes.ecommet.pages.errors.layout', ['title' => __st('errors.maintenance.title')])
@php
    $retrySeconds = isset($exception) && method_exists($exception, 'getHeaders') ? ($exception->getHeaders()['Retry-After'] ?? null) : null;
    $back = is_numeric($retrySeconds) ? now()->addSeconds((int) $retrySeconds) : null;
@endphp

@section('content')
    <span class="grid size-[72px] place-items-center rounded-full bg-[#fff3cd] text-[#b7791f]" aria-hidden="true"><x-ui::icon name="settings" :size="32" /></span>
    <h1 class="mt-2 text-2xl font-semibold text-sf-ink lg:text-[28px]">{{ __st('errors.maintenance.title') }}</h1>
    <p class="text-sm leading-6 text-sf-muted">
        {{ filled($maintenanceMessage ?? null) ? $maintenanceMessage : __st('errors.maintenance.body') }}
        @if ($back)
            {{ __st('errors.maintenance.back_at', ['time' => $back->translatedFormat('H:i').' ('.$back->format('T').')']) }}
        @endif
    </p>
    <a href="{{ request()->fullUrl() }}" class="sf-btn sf-btn-outline mo-press mt-2 rounded-full px-6"><x-ui::icon name="refresh" :size="18" />{{ __st('errors.refresh') }}</a>
@endsection
