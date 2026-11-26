{{-- Ecommet locale entry (1:444): flag · "En/" · currency ⌄ — opens the preferences panel. --}}
@props(['tone' => 'light', 'compact' => false])
@php
    $context = app(\App\Modules\Storefront\Services\StorefrontContext::class);
    $country = $context->country();
@endphp
<button type="button" data-sheet-open="sf-preferences" {{ $attributes->class(['inline-flex shrink-0 items-center gap-1.5 text-start', 'text-white' => $tone === 'light', 'text-eco-black' => $tone !== 'light']) }} aria-haspopup="dialog" aria-label="{{ __st('shell.preferences.open', ['language' => strtoupper(app()->getLocale()), 'currency' => $context->currencyCode()]) }}">
    @if ($country && ! $compact)
        <span class="grid h-[25px] w-10 place-items-center overflow-hidden rounded-sm bg-white/15 text-xl leading-none" aria-hidden="true">{{ $country->flag }}</span>
    @endif
    <span class="grid text-xs leading-tight tracking-[0.5px]">
        <span class="font-light">{{ ucfirst(app()->getLocale()) }}/</span>
        <span class="inline-flex items-center gap-0.5 font-medium">{{ $context->currencyCode() }}<x-ui::icon name="chevron-down" :size="12" /></span>
    </span>
</button>
