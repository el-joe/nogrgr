{{--
    Shared error body (Figma 404 127:12974 / 500 127:13129 / 419 127:13167 / 503 127:13205):
    big gradient code or icon disc, title, text and up to two actions.
    Inputs: $code (?string), $icon (?string), $title, $text, $primary [url,label], $secondary [url,label].
--}}
<section class="relative flex min-h-[420px] flex-col items-center justify-center overflow-hidden px-4 py-16 text-center lg:min-h-[480px] lg:py-[72px]" aria-labelledby="error-title">
    <span class="site-glow site-glow--page" aria-hidden="true"></span>
    @if (! empty($code))
        <p class="site-error-code relative text-[96px] leading-none font-bold tracking-tight lg:text-[160px]" aria-hidden="true">{{ $code }}</p>
    @elseif (! empty($icon))
        <span class="relative grid size-16 place-items-center rounded-full bg-site-tint text-primary lg:size-20" aria-hidden="true"><x-ui::icon :name="$icon" :size="32" class="site-spin-slow" /></span>
    @endif
    <h1 id="error-title" class="relative mt-4 text-2xl font-bold text-site-ink lg:mt-6 lg:text-[32px]" data-reveal="fade-up">{{ $title }}</h1>
    <p class="relative mt-3 max-w-md text-sm text-site-gray lg:text-base" data-reveal="fade-up" data-reveal-delay="80">{{ $text }}</p>
    <div class="relative mt-6 flex flex-wrap justify-center gap-2" data-reveal="fade-up" data-reveal-delay="160">
        @if (! empty($primary))
            <a href="{{ $primary[0] }}" class="site-btn inline-flex h-10 items-center justify-center rounded-lg bg-primary px-6 text-sm font-medium text-white hover:bg-primary-hover">{{ $primary[1] }}</a>
        @endif
        @if (! empty($secondary))
            <a href="{{ $secondary[0] }}" class="site-btn inline-flex h-10 items-center justify-center rounded-lg border border-primary bg-white px-6 text-sm font-medium text-primary hover:bg-site-tint">{{ $secondary[1] }}</a>
        @endif
    </div>
</section>
