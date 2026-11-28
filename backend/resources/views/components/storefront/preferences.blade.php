{{--
    Language / currency / shipping-country panel (opened by x-sf::locale-button). Posts to
    storefront.preferences, which keeps the choice in the session and moves the cart.
--}}
@php
    $context = app(\App\Modules\Storefront\Services\StorefrontContext::class);
    $locales = \App\Shared\Support\Locale::enabled();
    $currencies = app(\App\Modules\Localization\Services\StoreCurrencyService::class)->switcherCurrencies();
    $countries = app(\App\Modules\Localization\Services\StoreCountries::class)->available()
        ->map(fn ($country) => ['id' => $country->id, 'iso2' => strtolower($country->iso2), 'flag' => $country->flag, 'name' => $country->name])->values()->all();
    $redirect = '/'.ltrim(request()->path() === '/' ? '' : request()->path(), '/');
    /* The compact panel + searchable picker belong to the Elora design (every variant); the other
       themes keep their own panel look (native select). */
    $eloraBase = $context->theme()->isElora();
    /* Flag of each currency: the country the code starts with (ISO 4217), when it uses it. */
    $flags = $currencies->mapWithKeys(static fn ($currency): array => [$currency->code => $currency->code === 'EUR' ? 'eu' : strtolower(substr($currency->code, 0, 2))]);
@endphp
@if (Route::has('storefront.preferences'))
    <x-sf::modal id="sf-preferences" :title="__st('shell.preferences.title')" size="sm" :class="$eloraBase ? 'sf-pref-panel' : ''" data-lazy-account>
        <form method="POST" action="{{ route('storefront.preferences') }}" class="grid gap-5" data-preferences-form>
            @csrf
            <input type="hidden" name="redirect" value="{{ $redirect }}">
            <fieldset class="grid gap-2">
                <legend class="sf-label mb-2">{{ __st('shell.preferences.language') }}</legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ($locales as $code => $locale)
                        <label class="flex cursor-pointer items-center gap-2 rounded-sf-input border border-sf-border px-3 py-2.5 text-sm has-[:checked]:border-sf-cta has-[:checked]:shadow-[inset_0_0_0_1px_var(--sf-cta)]">
                            <input type="radio" name="locale" value="{{ $code }}" class="accent-[var(--sf-cta)]" @checked(app()->getLocale() === $code)>
                            <span lang="{{ $code }}">{{ $locale['native'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            @if ($currencies->isNotEmpty())
                <x-sf::form.field :label="__st('shell.preferences.currency')" for="sf-pref-currency" class="sf-pref-currency">
                    <select id="sf-pref-currency" name="currency" class="sf-control" @if ($eloraBase) data-currency-select @endif data-empty-text="{{ __st('shell.preferences.no_currency') }}" data-search-text="{{ __st('shell.preferences.search_currency') }}">
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->code }}" data-symbol="{{ $currency->symbol }}" data-iso2="{{ $flags[$currency->code] }}" data-name="{{ $currency->name }}" @selected($context->currencyCode() === $currency->code)>{{ $currency->code }} · {{ $currency->name }}</option>
                        @endforeach
                    </select>
                </x-sf::form.field>
            @endif
            @if (count($countries) > 1)
                <x-sf::form.country-select name="country_id" :label="__st('shell.preferences.country')" :countries="$countries" :value="$context->countryId()" :use-old="false" />
            @endif
            <button type="submit" class="sf-btn sf-btn-cta sf-btn-lg w-full">{{ __st('shell.preferences.save') }}</button>
        </form>
    </x-sf::modal>
@endif
