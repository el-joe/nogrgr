{{--
    Storefront registration ({store}/account/register), Figma Storefront additions §4.
    Inputs: $redirect (same-host target or null), $phoneCountry (ISO2 pre-selected).
--}}
<x-sf::auth.card :title="__('storefront::account.create_account')" active="register" :redirect="$redirect">
    <form method="POST" action="{{ route('storefront.account.register.store') }}" class="grid gap-4" data-ajax data-register-form novalidate>
        @csrf
        @if ($redirect)
            <input type="hidden" name="redirect" value="{{ $redirect }}">
        @endif
        <x-sf::form.input name="name" :label="__('identity::account.fields.name')" autocomplete="name" required maxlength="120" autofocus />
        <x-sf::form.input name="email" type="email" :label="__('identity::account.fields.email')" autocomplete="email" required dir="ltr" />
        <x-sf::form.phone :label="__('identity::account.fields.phone')" :country="$phoneCountry" />
        <x-sf::form.input name="password" type="password" :label="__('identity::account.fields.password')" autocomplete="new-password" required minlength="8" dir="ltr" :helper="__('storefront::account.password_hint')" />
        <x-sf::form.input name="password_confirmation" type="password" :label="__('identity::account.fields.password_confirmation')" autocomplete="new-password" required minlength="8" dir="ltr" />
        <x-sf::form.button type="submit" size="lg" block :busy="__('storefront::account.creating')">{{ __('identity::account.register') }}</x-sf::form.button>
    </form>

    <x-sf::auth.social :redirect="$redirect" />
    <p class="text-xs text-sf-muted">{{ __('storefront::account.agree') }}</p>
</x-sf::auth.card>
