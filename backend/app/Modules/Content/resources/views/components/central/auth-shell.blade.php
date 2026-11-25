{{--
    Central auth layout (Figma Login 12:42268 / m 12:43512, Forgot 127:12477, Sent
    127:12646, Reset 127:12754, Invalid 127:12867): heading with an orange highlight +
    lead text beside a white 480px card (radius 24, soft shadow) inside the brand glow.
    Props: seo, title (plain text), highlight (orange part), text; slot = card body.
--}}
@props(['seo' => null, 'title', 'highlight' => null, 'text' => null, 'page' => 'site-auth'])
@php
    $heading = $highlight !== null ? str_replace(':highlight', '<span class="text-primary">'.e($highlight).'</span>', e($title)) : e($title);
@endphp
<x-central::layout :seo="$seo" :title="html_entity_decode(strip_tags($heading))" :page="$page">
    <section class="relative mx-auto grid max-w-[1264px] items-center gap-8 px-4 pt-6 pb-16 lg:grid-cols-[1fr_480px] lg:gap-16 lg:px-[115px] lg:pt-[69px] lg:pb-[61px]">
        <span class="site-glow site-glow--page" aria-hidden="true"></span>
        <div class="relative text-center lg:text-start" data-reveal="fade-start">
            <h1 class="text-2xl font-medium text-site-ink lg:text-[32px]">{!! $heading !!}</h1>
            @if ($text)
                <p class="mx-auto mt-3 max-w-[420px] text-sm text-site-gray lg:mx-0 lg:mt-4 lg:text-xl">{{ $text }}</p>
            @endif
        </div>
        <div {{ $attributes->class(['relative rounded-3xl bg-white p-5 shadow-[0_8px_40px_rgba(65,37,30,0.08)] lg:p-10']) }} data-reveal="fade-up" data-reveal-delay="100">
            {{ $slot }}
        </div>
    </section>
</x-central::layout>
