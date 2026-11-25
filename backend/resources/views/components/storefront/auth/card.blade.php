{{-- Auth card (Figma Storefront additions §4): title, Login / Register segmented tabs, body. --}}
@props(['title', 'tabs' => true, 'active' => 'login', 'redirect' => null])
<x-sf::layout :title="$title" :mobile-title="__st('account.sign_in_title')" :back="Route::has('storefront.home') ? route('storefront.home') : null" nav="account">
    <div class="mx-auto w-full max-w-[460px] px-4 py-6 sm:py-12">
        <section class="sf-card grid gap-5 p-6 sm:p-7" aria-labelledby="sf-auth-title" data-reveal="fade-up">
            <h1 id="sf-auth-title" class="text-2xl font-semibold text-sf-ink">{{ $title }}</h1>
            @if ($tabs)
                <nav class="sf-segmented" aria-label="{{ __('identity::account.auth_tabs') }}">
                    <a href="{{ route('storefront.account.login', array_filter(['redirect' => $redirect])) }}" @if ($active === 'login') aria-current="page" @endif>{{ __('identity::auth.login') }}</a>
                    <a href="{{ route('storefront.account.register', array_filter(['redirect' => $redirect])) }}" @if ($active === 'register') aria-current="page" @endif>{{ __st('account.register_tab') }}</a>
                </nav>
            @endif
            {{ $slot }}
        </section>
    </div>
</x-sf::layout>
