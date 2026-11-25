{{--
    Reset password ({store}/account/reset-password/{token}), Figma Storefront additions §4.
    Inputs: $token, $email. An invalid / expired link shows the expired banner with a
    link to request a new one.
--}}
<x-sf::auth.card :title="__('storefront::account.reset_title')" :tabs="false">
    <form method="POST" action="{{ route('storefront.account.password.store') }}" class="grid gap-4" data-ajax data-reset-form novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @if ($email)
            <input type="hidden" name="email" value="{{ $email }}">
            <p class="text-sm text-sf-muted">{{ __('storefront::account.reset_for', ['email' => $email]) }}</p>
        @else
            <x-sf::form.input name="email" type="email" :label="__('identity::account.fields.email')" autocomplete="username" required dir="ltr" />
        @endif
        <x-sf::form.input name="password" type="password" :label="__('identity::auth.reset.new_password')" autocomplete="new-password" required minlength="8" dir="ltr" :helper="__('storefront::account.password_hint')" />
        <x-sf::form.input name="password_confirmation" type="password" :label="__('identity::account.fields.password_confirmation')" autocomplete="new-password" required minlength="8" dir="ltr" />
        <x-sf::form.button type="submit" size="lg" block>{{ __('storefront::account.update_password') }}</x-sf::form.button>
    </form>

    <x-sf::alert tone="danger" data-expired-state hidden>
        {{ __('storefront::account.reset_expired') }}
        <a href="{{ route('storefront.account.password.request') }}" class="sf-link">{{ __('storefront::account.request_new_link') }}</a>
    </x-sf::alert>
</x-sf::auth.card>
