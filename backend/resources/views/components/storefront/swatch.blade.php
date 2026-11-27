{{--
    Round colour swatch shared by every storefront colour UI (filter drawer, pickers). `hex` paints the disc
    (--swatch, set by core/swatches.js: no inline style); without a hex it is a neutral disc with the first
    letter. `light` = dark check / letter on light colours (white gets a thin visible ring). The check mark
    shows when an enclosing .sf-swatch-opt input is checked, or aria-checked="true" is set on the swatch.
--}}
@props(['hex' => null, 'label' => '', 'light' => false])
<span {{ $attributes->class(['sf-swatch']) }} @if ($hex) data-swatch="{{ $hex }}" @endif @if ($light) data-light @endif @if (! $hex) data-letter @endif aria-hidden="true">
    @if ($hex)<x-ui::icon name="check" :size="16" class="sf-swatch-check" />@else<span class="sf-swatch-letter">{{ mb_strtoupper(mb_substr($label, 0, 1)) }}</span>@endif
</span>
