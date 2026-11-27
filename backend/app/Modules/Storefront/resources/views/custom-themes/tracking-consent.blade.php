{{--
    Tracking consent on custom Twig theme pages (decisions #91 + #121), printed by
    {{ scripts() }}: the #sf-tracking island read by storefront/core/tracking.js (imported
    by the custom theme runtime) and a self-contained notice styled by
    resources/css/storefront/custom-theme-consent.css (the theme's own CSS is not ours).
    The CSP opens the pixel hosts only after "Accept" (CustomThemeCsp::allowTracking).
--}}
<script type="application/json" id="sf-tracking">{!! json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<section class="nt-consent" role="dialog" aria-modal="false" aria-labelledby="nt-consent-title" aria-describedby="nt-consent-text" data-tracking-consent hidden>
    <h2 id="nt-consent-title" class="nt-consent__title">{{ __st('appearance.consent.title') }}</h2>
    <p id="nt-consent-text" class="nt-consent__text">
        {{ __st('appearance.consent.text') }}
        @if (Route::has('storefront.pages.show'))
            <a href="{{ route('storefront.pages.show', ['slug' => 'privacy']) }}" class="nt-consent__link">{{ __st('appearance.consent.privacy') }}</a>
        @endif
    </p>
    <div class="nt-consent__actions">
        <button type="button" class="nt-consent__button" data-consent="denied">{{ __st('appearance.consent.decline') }}</button>
        <button type="button" class="nt-consent__button nt-consent__button--accept" data-consent="granted">{{ __st('appearance.consent.accept') }}</button>
    </div>
</section>
