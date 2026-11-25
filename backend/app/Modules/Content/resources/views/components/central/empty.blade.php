{{--
    Empty state for central pages: tinted icon disc, title, text and an optional action.
--}}
@props(['icon' => 'search', 'title', 'text' => null, 'actionUrl' => null, 'actionLabel' => null])
<div {{ $attributes->class(['mx-auto flex max-w-md flex-col items-center gap-3 px-4 py-12 text-center']) }} data-reveal="zoom-in" role="status">
    <span class="grid size-16 place-items-center rounded-full bg-site-tint text-primary"><x-ui::icon :name="$icon" :size="28" /></span>
    <h2 class="text-xl font-medium text-site-ink">{{ $title }}</h2>
    @if ($text)
        <p class="text-base text-site-gray">{{ $text }}</p>
    @endif
    @if ($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="site-btn mt-2 inline-flex h-10 items-center justify-center rounded-2xl bg-primary px-8 text-base font-medium text-white hover:bg-primary-hover">{{ $actionLabel }}</a>
    @endif
</div>
