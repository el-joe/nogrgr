{{--
    Inline SVG icon from resources/svg/icons (Figma Icons frame 24:10147).
    <x-ui::icon name="cart" :size="20" class="text-ink-muted" />   label="…" makes it non-decorative.
--}}
@props(['name', 'size' => 20, 'label' => null])
{!! \App\Shared\Support\Icon::svg($name, (int) $size, (string) $attributes->class(['ui-icon']), $label) !!}
