{{--
    Section heading (Figma Md-32 #41251E, centered) with optional subtitle (Reg-20/24 gray).
    <x-central::section-title :title="…" :text="…" as="h2" />
--}}
@props(['title', 'text' => null, 'as' => 'h2', 'id' => null])
<div {{ $attributes->class(['mx-auto flex max-w-[701px] flex-col items-center gap-2 text-center lg:gap-4']) }} data-reveal="fade-up">
    <{{ $as }} @if ($id) id="{{ $id }}" @endif class="text-2xl font-medium text-site-ink lg:text-[32px]">{{ $title }}</{{ $as }}>
    @if ($text)
        <p class="text-base text-site-gray lg:text-xl">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
