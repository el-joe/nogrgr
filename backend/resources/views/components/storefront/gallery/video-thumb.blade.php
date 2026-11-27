{{--
    Thumbnail of a gallery video (#114): the poster (or the first frame) with a play badge.
--}}
@props(['item', 'width' => 80, 'height' => 80])
<span class="sf-video-thumb">
    @if (! empty($item['poster']))
        <img src="{{ $item['poster'] }}" alt="" width="{{ $width }}" height="{{ $height }}" loading="lazy" decoding="async">
    @else
        <video src="{{ $item['url'] }}#t=0.1" muted playsinline preload="metadata" tabindex="-1" aria-hidden="true"></video>
    @endif
    <span class="sf-video-thumb-badge" aria-hidden="true"><x-ui::icon name="play" :size="14" /></span>
    <span class="sr-only">{{ __st('product.video') }}</span>
</span>
