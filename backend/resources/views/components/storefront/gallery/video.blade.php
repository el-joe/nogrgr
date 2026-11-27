{{--
    Product gallery video slide content (#114), shared by every theme family: native
    player (controls, inline on phones, metadata only until played), contained like
    the images. Swiper keeps swiping over it; the floating popup (core/video-pip.js)
    reads [data-gallery-video].
    <x-sf::gallery.video :item="['url' => …, 'poster' => …]" :index="2" :name="$page->name" />
--}}
@props(['item', 'index' => 0, 'name' => ''])
<video src="{{ $item['url'] }}" @if (! empty($item['poster'])) poster="{{ $item['poster'] }}" @endif controls playsinline preload="metadata" class="sf-gallery-video size-full object-contain" data-gallery-video aria-label="{{ __st('product.video_label', ['name' => $name, 'index' => $index + 1]) }}" {{ $attributes }}>
    <a href="{{ $item['url'] }}">{{ __st('product.video_fallback') }}</a>
</video>
