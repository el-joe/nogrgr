{{--
    About (Figma 12:41879, mobile 12:43671): brand lead + story paragraphs beside the
    merchant photo on an orange disc with two soft bubbles; then the admin-edited page
    body ($page, sanitized HTML) when present. Data: $seo, $page (?Page).
--}}
@php
    $appName = config('app.name');
    $brand = '<span class="text-primary">'.e($appName).'</span>';
@endphp
<x-central::layout :seo="$seo" page="site-about">
    <section class="relative mx-auto max-w-[1440px] px-4 pt-6 pb-10 lg:flex lg:min-h-[505px] lg:items-start lg:justify-between lg:gap-10 lg:px-[82px] lg:pt-[56px] lg:pb-8" aria-labelledby="about-title">
        <div class="flex flex-col gap-6 text-center lg:mt-[43px] lg:w-[676px] lg:gap-10 lg:text-start">
            <h1 id="about-title" class="text-base leading-[29px] font-medium text-site-ink lg:max-w-[518px]" data-reveal="fade-up">{!! __('content::site.about.lead', ['brand' => $brand]) !!}</h1>
            <div class="flex flex-col text-xs leading-[1.7] text-site-gray lg:text-base lg:leading-[35px]" data-reveal="fade-up" data-reveal-delay="120">
                @foreach (__('content::site.about.paragraphs') as $paragraph)
                    <p>{{ str_replace(':app', $appName, $paragraph) }}</p>
                @endforeach
            </div>
        </div>
        <figure class="relative mx-auto mt-10 aspect-[487/418] w-full max-w-[340px] shrink-0 lg:mx-0 lg:mt-0 lg:w-[487px] lg:max-w-none" data-reveal="zoom-in" data-reveal-delay="150">
            <span class="absolute start-[6.16%] top-[6.46%] aspect-square w-[78%] rounded-full bg-primary" aria-hidden="true"></span>
            <span class="site-bubble absolute end-0 top-[23.9%] aspect-square w-[28.3%] rounded-full bg-[#ffe7e1]" aria-hidden="true"></span>
            <span class="site-bubble site-bubble--b absolute start-[70.2%] top-0 aspect-square w-[11.1%] rounded-full bg-[#ffe1da]" aria-hidden="true"></span>
            <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/central/pages/about-person.webp') }}" alt="{{ __('content::site.about.image_alt') }}" width="510" height="457" fetchpriority="high"
                class="absolute start-0 top-0 h-full w-[95.9%] object-contain">
        </figure>
    </section>
    @if (! empty($page?->body))
        <section class="mx-auto max-w-[900px] px-4 pb-16 lg:pb-[72px]" aria-label="{{ $page->title ?? __('content::site.about.title') }}" data-reveal="fade-up">
            <div class="site-prose site-prose--lg">{!! $page->body !!}</div>
        </section>
    @endif
</x-central::layout>
