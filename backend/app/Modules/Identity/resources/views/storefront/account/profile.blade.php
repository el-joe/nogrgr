{{--
    Account › Profile (Figma Storefront additions §6): personal details view ⇄ edit,
    change password, delete account (confirm modal with password). Inputs: $customer,
    $locales (code => [name, native, dir]), $phoneCountry (ISO2). All three forms post as
    JSON; the delete-account password error is rendered from its own 422 response, so it
    never shows on the change-password form that shares the `password` key.
--}}
@php
    $localeName = $locales[$customer->locale ?? app()->getLocale()]['native'] ?? ($customer->locale ?? app()->getLocale());
@endphp
<x-sf::account.layout :title="__('identity::account.profile.title')" active="profile" page="profile">
    <section class="sf-card grid gap-4 p-5" aria-labelledby="profile-details" data-profile-card>
        <div class="flex items-center justify-between gap-3">
            <h2 id="profile-details" class="text-lg font-semibold text-sf-ink">{{ __('identity::account.profile.details') }}</h2>
            <span class="text-xs font-medium text-sf-accent" data-profile-editing hidden>{{ __st('account.editing') }}</span>
            <button type="button" class="sf-btn sf-btn-outline sf-btn-sm" data-profile-edit><x-ui::icon name="edit" :size="16" />{{ __st('account.edit') }}</button>
        </div>

        <dl class="grid gap-4 sm:grid-cols-2" data-profile-view>
            <div class="grid gap-1"><dt class="text-xs text-sf-muted">{{ __('identity::account.fields.name') }}</dt><dd class="text-sm text-sf-ink" data-profile-value="name">{{ $customer->name }}</dd></div>
            <div class="grid gap-1"><dt class="text-xs text-sf-muted">{{ __('identity::account.fields.phone') }}</dt><dd class="text-sm text-sf-ink" dir="ltr" data-profile-value="phone">{{ $customer->phone ?: '—' }}</dd></div>
            <div class="grid gap-1"><dt class="text-xs text-sf-muted">{{ __('identity::account.fields.email') }}</dt><dd class="text-sm text-sf-ink" dir="ltr" data-profile-value="email">{{ $customer->email }}</dd></div>
            <div class="grid gap-1"><dt class="text-xs text-sf-muted">{{ __('identity::account.fields.locale') }}</dt><dd class="text-sm text-sf-ink" data-profile-value="locale" data-locale-names='@json(collect($locales)->map(fn ($language) => $language['native']))'>{{ $localeName }}</dd></div>
        </dl>

        <form method="POST" action="{{ route('storefront.account.profile.update') }}" class="grid gap-4" data-ajax data-profile-form hidden novalidate>
            @csrf
            @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <x-sf::form.input name="name" :label="__('identity::account.fields.name')" :value="$customer->name" autocomplete="name" required maxlength="120" />
                <x-sf::form.phone :label="__('identity::account.fields.phone')" :value="$customer->phone" :country="$phoneCountry" />
                <x-sf::form.input name="email" type="email" :label="__('identity::account.fields.email')" :value="$customer->email" autocomplete="email" required dir="ltr" />
                <x-sf::form.field :label="__('identity::account.fields.locale')" for="sf-locale" error-key="locale">
                    <div class="sf-control">
                        <select id="sf-locale" name="locale">
                            @foreach ($locales as $code => $language)
                                <option value="{{ $code }}" @selected(($customer->locale ?? app()->getLocale()) === $code)>{{ $language['native'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-sf::form.field>
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                <x-sf::form.button variant="outline" data-profile-cancel>{{ __st('account.cancel') }}</x-sf::form.button>
                <x-sf::form.button type="submit">{{ __('identity::account.save') }}</x-sf::form.button>
            </div>
        </form>
    </section>

    <section class="sf-card grid gap-4 p-5" aria-labelledby="profile-password">
        <h2 id="profile-password" class="text-lg font-semibold text-sf-ink">{{ __('identity::account.password.title') }}</h2>
        <form method="POST" action="{{ route('storefront.account.password.update') }}" class="grid gap-4" data-ajax data-password-form data-reset-on-success novalidate>
            @csrf
            @method('PUT')
            <x-sf::form.input name="current_password" type="password" :label="__('identity::account.fields.current_password')" autocomplete="current-password" required dir="ltr" />
            <x-sf::form.input name="password" type="password" :label="__('identity::account.fields.new_password')" autocomplete="new-password" required minlength="8" dir="ltr" :helper="__st('account.password_hint')" />
            <x-sf::form.input name="password_confirmation" type="password" :label="__st('account.confirm_new_password')" autocomplete="new-password" required minlength="8" dir="ltr" />
            <div><x-sf::form.button type="submit">{{ __('identity::account.password.submit') }}</x-sf::form.button></div>
        </form>
    </section>

    <section class="sf-card grid gap-3 p-5" aria-labelledby="profile-delete">
        <h2 id="profile-delete" class="text-lg font-semibold text-sf-ink">{{ __('identity::account.delete.title') }}</h2>
        <p class="text-sm text-sf-muted">{{ __('identity::account.delete.warning') }}</p>
        <div><x-sf::form.button variant="danger-outline" icon="trash" data-sheet-open="sf-delete-account">{{ __('identity::account.delete.submit') }}</x-sf::form.button></div>
    </section>

    <x-sf::modal id="sf-delete-account" :title="__st('account.delete_title')" size="sm">
        <form method="POST" action="{{ route('storefront.account.destroy') }}" class="grid gap-4" data-ajax data-delete-account-form novalidate>
            @csrf
            @method('DELETE')
            <p class="text-sm text-sf-muted">{{ __st('account.delete_message') }}</p>
            <x-sf::form.input name="password" id="sf-delete-password" type="password" :label="__st('account.delete_password')" autocomplete="current-password" required dir="ltr" :use-old="false" :server-error="false" />
            <div class="grid grid-cols-2 gap-3">
                <x-sf::form.button variant="outline" data-sheet-close>{{ __st('account.cancel') }}</x-sf::form.button>
                <x-sf::form.button type="submit" variant="danger" :busy="__st('account.deleting')">{{ __st('account.delete_confirm') }}</x-sf::form.button>
            </div>
        </form>
    </x-sf::modal>
</x-sf::account.layout>
