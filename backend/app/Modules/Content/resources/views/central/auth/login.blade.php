{{--
    Central login (Figma 12:42268, mobile 12:43512). Data: $seo, $action, $store (?string),
    $forgotUrl, $registerUrl, $socialUrls (provider => url), session('status').
--}}
@php
    $appName = config('app.name');
    $socialUrls = $socialUrls ?? [];
@endphp
<x-central::auth-shell :seo="$seo ?? null" :title="__('content::site.auth.aside_title', ['brand' => ':highlight'])" :highlight="$appName" :text="__('content::site.auth.aside_text')" page="site-login">
    <h2 class="text-xl font-medium text-site-ink">{{ __('content::site.auth.login_title') }}</h2>
    @if (session('status'))
        <p class="mt-4 rounded-lg bg-success-bg px-4 py-3 text-sm text-success" role="status">{{ session('status') }}</p>
    @endif
    <form method="POST" action="{{ $action }}" class="mt-6 flex flex-col gap-4" novalidate>
        @csrf
        @if (! empty($store))<input type="hidden" name="store" value="{{ $store }}">@endif
        <x-central::auth-input name="email" type="email" :label="__('content::site.auth.email')" :value="old('email')" autocomplete="username" autofocus />
        <x-central::auth-input name="password" type="password" :label="__('content::site.auth.password')" autocomplete="current-password" password />
        <div class="flex items-center justify-between gap-3 text-sm">
            <label class="inline-flex items-center gap-2 text-site-gray">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded accent-primary" @checked(old('remember'))>
                {{ __('content::site.auth.remember') }}
            </label>
            @if (! empty($forgotUrl))
                <a href="{{ $forgotUrl }}" class="site-link text-primary">{{ __('content::site.auth.forgot') }}</a>
            @endif
        </div>
        <button type="submit" class="site-btn h-12 rounded-xl bg-primary text-base font-medium text-white hover:bg-primary-hover">{{ __('content::site.auth.submit') }}</button>
    </form>
    @if (! empty($socialUrls))
        <div class="mt-6 flex items-center gap-4 text-base text-site-gray" aria-hidden="true">
            <span class="h-px flex-1 bg-site-border"></span>{{ __('content::site.auth.or_with') }}<span class="h-px flex-1 bg-site-border"></span>
        </div>
        <div class="mt-5 grid grid-cols-2 gap-3">
            @foreach ($socialUrls as $provider => $url)
                <a href="{{ $url }}" class="site-btn flex h-10 items-center justify-center gap-2 rounded-lg border border-site-border px-3 text-xs text-site-ink hover:border-primary">
                    <x-ui::icon :name="$provider === 'apple' ? 'apple' : 'google'" :size="18" />
                    <span class="truncate">{{ __('content::site.auth.with_'.($provider === 'apple' ? 'apple' : 'google')) }}</span>
                </a>
            @endforeach
        </div>
    @endif
    @if (! empty($registerUrl))
        <p class="mt-6 text-center text-sm text-site-gray">{{ __('content::site.auth.no_account', ['app' => $appName]) }} <a href="{{ $registerUrl }}" class="site-link font-medium text-primary">{{ __('content::site.auth.register') }}</a></p>
    @endif
</x-central::auth-shell>
