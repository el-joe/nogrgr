{{--
    Storefront sign-in ({store}/account/login), Figma Storefront additions §4. Inputs:
    $redirect (same-host target or null), $socialError (flash from a failed social sign-in).
    Submits as JSON (data-ajax): 422 field errors render inline, success follows `redirect`.
--}}
<x-sf::auth.card :title="__st('account.welcome_back')" active="login" :redirect="$redirect">
    @if (session('status'))
        <x-sf::alert tone="success">{{ session('status') }}</x-sf::alert>
    @endif
    @if ($socialError)
        <x-sf::alert tone="danger">{{ $socialError }}</x-sf::alert>
    @endif

    <form method="POST" action="{{ route('storefront.account.login.store') }}" class="grid gap-4" data-ajax data-login-form novalidate>
        @csrf
        @if ($redirect)
            <input type="hidden" name="redirect" value="{{ $redirect }}">
        @endif
        <x-sf::form.input name="email" type="email" :label="__('identity::account.fields.email')" autocomplete="username" required dir="ltr" autofocus />
        <x-sf::form.input name="password" type="password" :label="__('identity::auth.password')" autocomplete="current-password" required dir="ltr" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-sf-ink"><input type="checkbox" name="remember" value="1" class="sf-check" @checked(old('remember'))>{{ __('identity::auth.remember') }}</label>
            <a href="{{ route('storefront.account.password.request') }}" class="sf-link text-sm">{{ __('identity::auth.forgot') }}</a>
        </div>
        <x-sf::form.button type="submit" size="lg" block :busy="__st('account.signing_in')">{{ __('identity::auth.login') }}</x-sf::form.button>
    </form>

    <x-sf::auth.social :redirect="$redirect" />
</x-sf::auth.card>
