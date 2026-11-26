{{-- Header account entry (1:452): "Welcome / Sign in · Register" for guests, the customer's name when signed in. --}}
@props(['tone' => 'dark', 'compact' => false])
@php
    $customer = auth('customer')->user();
    $href = $customer ? (Route::has('storefront.account.profile') ? route('storefront.account.profile') : null) : (Route::has('storefront.account.login') ? route('storefront.account.login') : null);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['inline-flex items-center gap-2 tracking-[0.5px]', 'text-white' => $tone === 'light', 'text-sf-ink' => $tone === 'dark']) }}>
        <x-ui::icon :name="\App\Modules\Storefront\Support\StorefrontTheme::family() === 'ecommet' ? 'user' : 'users'" :size="24" />
        @unless ($compact)
            <span class="grid text-start text-sm leading-tight">
                <span @class(['text-[#c5c5c5]' => $tone === 'light', 'text-sf-muted' => $tone === 'dark'])>{{ $customer ? __st('shell.hello') : __st('shell.welcome') }}</span>
                <span class="max-w-[160px] truncate">{{ $customer ? $customer->name : __st('shell.sign_in_register') }}</span>
            </span>
        @endunless
    </a>
@endif
