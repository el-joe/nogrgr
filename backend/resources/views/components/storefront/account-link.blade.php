{{-- Header account entry: "Welcome / Sign in · Register" for guests, the customer's name when signed in. --}}
@props(['tone' => 'dark', 'compact' => false])
@php
    $customer = auth('customer')->user();
    $href = $customer ? (Route::has('storefront.account.profile') ? route('storefront.account.profile') : null) : (Route::has('storefront.account.login') ? route('storefront.account.login') : null);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['inline-flex items-center gap-2', 'text-white' => $tone === 'light', 'text-sf-ink' => $tone === 'dark']) }}>
        <x-ui::icon name="users" :size="24" />
        @unless ($compact)
            <span class="grid text-start leading-tight">
                <span @class(['text-sm', 'text-white/75' => $tone === 'light', 'text-sf-muted' => $tone === 'dark'])>{{ $customer ? __('storefront::shell.hello') : __('storefront::shell.welcome') }}</span>
                <span class="max-w-[160px] truncate text-sm">{{ $customer ? $customer->name : __('storefront::shell.sign_in_register') }}</span>
            </span>
        @endunless
    </a>
@endif
