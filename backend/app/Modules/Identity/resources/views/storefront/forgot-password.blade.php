{{--
    Forgot password ({store}/account/forgot-password), Figma Storefront additions §4:
    form → "sent" state (same answer whether or not the email exists).
--}}
<x-sf::auth.card :title="__('storefront::account.forgot_title')" :tabs="false">
    <p class="text-sm text-sf-muted">{{ __('storefront::account.forgot_intro') }}</p>

    <form method="POST" action="{{ route('storefront.account.password.email') }}" class="grid gap-4" data-ajax data-forgot-form novalidate>
        @csrf
        <x-sf::form.input name="email" type="email" :label="__('identity::account.fields.email')" autocomplete="username" required dir="ltr" autofocus />
        <x-sf::form.button type="submit" size="lg" block :busy="__('storefront::account.sending')">{{ __('identity::auth.reset.send_link') }}</x-sf::form.button>
    </form>

    <x-sf::alert tone="success" data-sent-state :hidden="! session('status')">{{ session('status') ?: __('identity::auth.reset.link_sent') }}</x-sf::alert>

    <a href="{{ route('storefront.account.login') }}" class="sf-link text-sm">{{ __('identity::auth.reset.back_to_login') }}</a>
</x-sf::auth.card>
