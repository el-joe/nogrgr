{{--
    Elora outlined check box (Figma node 1:17576: 26 px rounded square, black outline, black tick).
    A real <input type="checkbox"> (all attributes go to it) stays focusable / clickable; the Figma
    box + tick are SVG icons that follow its state. Styles: css/storefront/elora-cart.css (.elo-check).
--}}
@props(['wrap' => ''])
<span class="elo-check {{ $wrap }}">
    <input type="checkbox" {{ $attributes }}>
    <x-ui::icon name="elo-check-box" :size="26" class="elo-check-box" />
    <x-ui::icon name="elo-check-mark" :size="26" class="elo-check-mark" />
</span>
