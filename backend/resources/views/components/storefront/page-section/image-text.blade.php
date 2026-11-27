{{-- Store-page section "Image + text" (VEN-032): image (object-contain) beside title, text and an optional button; image start or end. --}}
@props(['section'])
<section class="grid items-center gap-6 rounded-sf-card border border-sf-border bg-sf-surface p-4 md:grid-cols-2 md:gap-10 md:p-10" aria-labelledby="ps-{{ $section['key'] }}">
    @if ($section['image'])
        <div @class(['overflow-hidden rounded-sf-card bg-sf-bg', 'md:order-last' => $section['image_side'] === 'end']) data-reveal="slide-start">
            <img src="{{ $section['image'] }}" alt="{{ $section['title'] ?? '' }}" class="h-auto max-h-[420px] w-full object-contain" loading="lazy" decoding="async">
        </div>
    @endif
    <div @class(['grid content-center gap-4', 'md:col-span-2' => ! $section['image']]) data-reveal="fade-up">
        <x-sf::page-section.heading :title="$section['title']" :id="'ps-'.$section['key']" />
        @if ($section['body'])<p class="whitespace-pre-line text-sm leading-6 text-sf-muted">{{ $section['body'] }}</p>@endif
        @if ($section['button'] && $section['url'])
            <a href="{{ $section['url'] }}" class="inline-flex w-fit items-center gap-2 rounded-sf-input bg-sf-accent px-5 py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-90">{{ $section['button'] }}<x-ui::icon name="arrow-right" :size="16" class="rtl:-scale-x-100" /></a>
        @endif
    </div>
</section>
