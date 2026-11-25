{{--
    Reset password (Figma 127:12754) and invalid/expired token (127:12867).
    Data: $seo, $action, $token, $email, $invalid (bool).
--}}
@php
    $forgotUrl = Route::has('central.password.request') ? route('central.password.request') : null;
    $loginUrl = Route::has('central.login') ? route('central.login') : null;
@endphp
@if ($invalid ?? false)
    <x-central::auth-shell :seo="$seo ?? null" :title="__('content::site.auth.invalid_title')" :highlight="__('content::site.auth.invalid_highlight')" :text="__('content::site.auth.invalid_text')" page="site-reset">
        <p class="flex items-center gap-2 rounded-lg bg-danger-bg px-4 py-3 text-xs text-site-ink" role="alert">
            <x-ui::icon name="x-circle" :size="16" class="shrink-0 text-danger" />{{ __('content::site.auth.invalid_alert') }}
        </p>
        @if ($forgotUrl)
            <a href="{{ $forgotUrl }}" class="site-btn mt-4 flex h-12 items-center justify-center rounded-xl bg-primary text-base font-medium text-white hover:bg-primary-hover">{{ __('content::site.auth.request_new') }}</a>
        @endif
    </x-central::auth-shell>
@else
    <x-central::auth-shell :seo="$seo ?? null" :title="__('content::site.auth.reset_title')" :highlight="__('content::site.auth.reset_highlight')" :text="__('content::site.auth.reset_text')" page="site-reset">
        <h2 class="text-base font-medium text-site-ink">{{ __('content::site.auth.reset_card') }}</h2>
        <form method="POST" action="{{ $action }}" class="mt-5 flex flex-col gap-4" novalidate>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-central::auth-input name="email" type="email" :label="__('content::site.auth.email_address')" :value="old('email', $email)" autocomplete="username" />
            <x-central::auth-input name="password" type="password" :label="__('content::site.auth.new_password')" autocomplete="new-password" password autofocus />
            <x-central::auth-input name="password_confirmation" type="password" :label="__('content::site.auth.confirm_password')" autocomplete="new-password" password />
            <button type="submit" class="site-btn h-12 rounded-xl bg-primary text-base font-medium text-white hover:bg-primary-hover">{{ __('content::site.auth.reset_submit') }}</button>
        </form>
        @if ($loginUrl)
            <a href="{{ $loginUrl }}" class="site-link mt-5 inline-block text-xs text-primary">{{ __('content::site.auth.back_to_login') }}</a>
        @endif
    </x-central::auth-shell>
@endif
