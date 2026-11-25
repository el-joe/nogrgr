{{--
    Newsletter strip (Figma 12:42512 / home 12:41799): title Md-24, subtitle Reg-16 gray,
    577×40 field with orange border + attached "Subscribe" button. Ajax form; success
    swaps to an inline confirmation (resources/js/central/site/forms.js).
--}}
@props(['variant' => 'strip'])
@php $action = \Illuminate\Support\Facades\Route::has('central.newsletter.store') ? route('central.newsletter.store') : null; @endphp
@if ($action)
    @if ($variant === 'band')
        {{-- Blog post band (Figma 127:12209): dark brown card. --}}
        <section {{ $attributes->class(['rounded-3xl bg-site-ink px-6 py-8 text-white lg:flex lg:items-center lg:justify-between lg:gap-10 lg:px-12']) }} data-reveal="fade-up" aria-labelledby="newsletter-band-title">
            <div class="mb-5 lg:mb-0">
                <h2 id="newsletter-band-title" class="text-xl font-medium lg:text-2xl">{{ __('content::site.newsletter.band_title') }}</h2>
                <p class="mt-1 text-sm text-white/70">{{ __('content::site.newsletter.band_text') }}</p>
            </div>
            <div class="w-full lg:max-w-[440px]" data-ajax-state>
                <form data-ajax data-inline-success method="POST" action="{{ $action }}" class="flex flex-col gap-3 sm:flex-row" novalidate>
                    @csrf
                    <x-central::honeypot />
                    <label class="sr-only" for="newsletter-band-email">{{ __('content::site.newsletter.label') }}</label>
                    <div class="flex-1" data-field>
                        <input id="newsletter-band-email" type="email" name="email" required autocomplete="email" placeholder="{{ __('content::site.newsletter.band_placeholder') }}"
                            class="site-input h-11 w-full rounded-lg border border-transparent bg-white px-4 text-sm text-site-ink placeholder:text-site-gray">
                    </div>
                    <button type="submit" class="site-btn h-11 shrink-0 rounded-lg bg-primary px-6 text-sm font-medium text-white hover:bg-primary-hover">{{ __('content::site.newsletter.submit') }}</button>
                </form>
                <div class="site-success" hidden data-success role="status">
                    <x-ui::icon name="check-circle" :size="22" />
                    <span>{{ __('content::site.newsletter.success') }}</span>
                </div>
            </div>
        </section>
    @else
        <section {{ $attributes->class(['site-newsletter relative overflow-hidden bg-white px-4 py-12 lg:py-[88px]']) }} aria-labelledby="newsletter-title">
            <span class="site-glow site-glow--corner" aria-hidden="true"></span>
            <div class="relative mx-auto flex max-w-[577px] flex-col items-center gap-6 text-center" data-reveal="fade-up" data-ajax-state>
                <div class="flex flex-col gap-4">
                    <h2 id="newsletter-title" class="text-2xl font-medium text-site-ink">{{ __('content::site.newsletter.title') }}</h2>
                    <p class="text-base text-site-gray">{{ __('content::site.newsletter.text') }}</p>
                </div>
                <form data-ajax data-inline-success method="POST" action="{{ $action }}" class="w-full" novalidate>
                    @csrf
                    <x-central::honeypot />
                    <label class="sr-only" for="newsletter-email">{{ __('content::site.newsletter.label') }}</label>
                    <div class="site-field flex h-10 w-full overflow-hidden rounded-lg border border-primary bg-white" data-field>
                        <input id="newsletter-email" type="email" name="email" required autocomplete="email" placeholder="{{ __('content::site.newsletter.placeholder') }}"
                            class="min-w-0 flex-1 bg-transparent px-5 text-sm tracking-[0.5px] text-site-ink outline-none placeholder:text-site-gray">
                        <button type="submit" class="site-btn w-[110px] shrink-0 bg-primary text-base text-white hover:bg-primary-hover">{{ __('content::site.newsletter.submit') }}</button>
                    </div>
                </form>
                <div class="site-success" hidden data-success role="status">
                    <x-ui::icon name="check-circle" :size="22" />
                    <span>{{ __('content::site.newsletter.success') }}</span>
                    <button type="button" class="ms-2 text-sm text-primary underline" data-success-reset>{{ __('content::site.newsletter.again') }}</button>
                </div>
            </div>
        </section>
    @endif
@endif
