{{--
    Tracking consent notice (Vendor › Settings › Tracking pixels, VEN-083, decision #91):
    shown while the store has at least one pixel on and the shopper has not answered yet.
    The bundled loader (storefront/core/tracking.js) reads #sf-tracking, keeps the answer in
    a first-party cookie and loads the pixels only after "Accept".
--}}
@props(['config'])
<script type="application/json" id="sf-tracking">{!! json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<section class="fixed inset-x-3 bottom-3 z-[60] mx-auto max-w-[560px] translate-y-4 rounded-[var(--sf-radius-card,12px)] border border-[var(--sf-border,#e8e8e8)] bg-[var(--sf-surface,#ffffff)] p-4 text-[var(--sf-ink,#242424)] opacity-0 shadow-[0_12px_32px_rgb(0_0_0/0.16)] transition duration-300 data-[open]:translate-y-0 data-[open]:opacity-100 motion-reduce:transition-none max-lg:bottom-20 sm:p-5" role="dialog" aria-modal="false" aria-labelledby="sf-consent-title" aria-describedby="sf-consent-text" data-tracking-consent hidden>
    <h2 id="sf-consent-title" class="text-base font-semibold">{{ __st('appearance.consent.title') }}</h2>
    <p id="sf-consent-text" class="mt-1 text-sm leading-6 text-[var(--sf-muted,#717171)]">
        {{ __st('appearance.consent.text') }}
        @if (Route::has('storefront.pages.show'))
            <a href="{{ route('storefront.pages.show', ['slug' => 'privacy']) }}" class="underline underline-offset-2">{{ __st('appearance.consent.privacy') }}</a>
        @endif
    </p>
    <div class="mt-4 flex flex-wrap justify-end gap-2">
        <button type="button" class="inline-flex h-10 items-center rounded-full border border-[var(--sf-border,#e8e8e8)] px-5 text-sm font-medium transition-colors hover:bg-[var(--sf-bg,#f5f5f5)]" data-consent="denied">{{ __st('appearance.consent.decline') }}</button>
        <button type="button" class="inline-flex h-10 items-center rounded-full bg-[var(--sf-cta,#242424)] px-5 text-sm font-medium text-[var(--sf-on-cta,#ffffff)] transition-colors hover:bg-[var(--sf-cta-hover,#000000)]" data-consent="granted">{{ __st('appearance.consent.accept') }}</button>
    </div>
</section>
