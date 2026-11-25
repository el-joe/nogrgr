{{-- Mobile app bar of the account / auth frames: back · title · cart (when the cart exists). --}}
@props(['title', 'back' => null])
<div class="flex h-14 items-center gap-3 border-b border-sf-border bg-white px-4">
    @if ($back)
        <a href="{{ $back }}" class="grid size-8 place-items-center text-sf-ink" aria-label="{{ __st('shell.back') }}"><x-ui::icon name="chevron-left" :size="22" class="rtl:-scale-x-100" /></a>
    @endif
    <p class="min-w-0 flex-1 truncate text-lg font-semibold text-sf-ink">{{ $title }}</p>
    <x-sf::lang-switch compact />
    <x-sf::header.cart compact />
</div>
