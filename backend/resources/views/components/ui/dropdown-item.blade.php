{{--
    Menu item: 36h, radius 6. tone="danger" items go last and use the danger colour;
    pass data-confirm="…" (and data-confirm-method="DELETE") to route through the Confirm Dialog.
--}}
@props(['href' => null, 'icon' => null, 'tone' => null, 'type' => 'button'])
@if ($href)
    <a href="{{ $href }}" role="menuitem" tabindex="-1" @if ($tone) data-tone="{{ $tone }}" @endif {{ $attributes->class(['ui-menu-item']) }}>
        @if ($icon)<x-ui::icon :name="$icon" :size="16" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </a>
@else
    <button type="{{ $type }}" role="menuitem" tabindex="-1" @if ($tone) data-tone="{{ $tone }}" @endif {{ $attributes->class(['ui-menu-item']) }}>
        @if ($icon)<x-ui::icon :name="$icon" :size="16" />@endif
        <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    </button>
@endif
