{{--
    How it works (Figma 12:41810, mobile 12:43441): merchant photo in a brand glow with
    four numbered step callouts (2px orange border, radius 16, number disc 40px).
    Data: $seo. Mobile: stacked step cards with alternating number corners.
--}}
@php
    $steps = __('content::site.how.steps');
    $positions = [
        ['card' => 'lg:start-[199px] lg:top-[104px]', 'badge' => 'start-[-8px] top-[-20px] lg:start-[-20px]'],
        ['card' => 'lg:start-[1010px] lg:top-[156px]', 'badge' => 'end-[-8px] top-[-20px] lg:end-[-20px]'],
        ['card' => 'lg:start-[941px] lg:top-[416px]', 'badge' => 'start-[-8px] top-[-19px] lg:start-auto lg:end-[-20px]'],
        ['card' => 'lg:start-[159px] lg:top-[495px]', 'badge' => 'end-[-8px] top-[-19px] lg:end-auto lg:start-[-20px]'],
    ];
@endphp
<x-central::layout :seo="$seo" page="site-how">
    <section class="relative overflow-hidden px-4 pt-6 pb-16 lg:mx-auto lg:h-[802px] lg:max-w-[1440px] lg:p-0" aria-labelledby="how-title">
        <h1 id="how-title" class="mb-10 text-center text-xl font-medium text-site-ink lg:sr-only">{{ __('content::site.how.title') }}</h1>
        <span class="site-glow absolute top-1/2 left-1/2 hidden size-[72px] -translate-1/2 bg-white/20 lg:block" aria-hidden="true"></span>
        <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/central/pages/how-it-works-person.webp') }}" alt="{{ __('content::site.how.image_alt') }}" width="1000" height="1000" fetchpriority="high"
            class="absolute bottom-0 left-1/2 hidden aspect-[586/661] h-[687px] w-auto -translate-x-1/2 object-contain lg:block" data-reveal="fade-up">
        <span class="absolute inset-x-0 bottom-0 hidden h-[352px] bg-gradient-to-b from-white/0 to-white lg:block" aria-hidden="true"></span>
        <ol class="relative mx-auto flex max-w-[343px] flex-col gap-6 sm:max-w-md lg:static lg:max-w-none">
            @foreach ($steps as $index => $step)
                <li class="site-step relative rounded-2xl border-2 border-primary bg-white px-8 py-4 text-center lg:absolute lg:w-[304px] lg:ps-8 lg:pe-11 {{ $positions[$index]['card'] ?? '' }}"
                    data-reveal="{{ $index % 2 === 0 ? 'slide-start' : 'slide-end' }}" data-reveal-delay="{{ $index * 140 }}">
                    <span class="site-step__num absolute grid size-10 place-items-center rounded-3xl bg-primary text-2xl font-bold text-white {{ $positions[$index]['badge'] ?? '' }}" data-step="{{ $index + 1 }}" aria-hidden="true">{{ $index + 1 }}</span>
                    <p class="text-sm leading-6 text-site-ink lg:text-base"><span class="sr-only">{{ $index + 1 }}. </span>{{ $step }}</p>
                </li>
            @endforeach
        </ol>
    </section>
</x-central::layout>
