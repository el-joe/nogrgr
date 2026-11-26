{{-- Library row thumbnail: poster of a ready ad (opens the preview) or a placeholder tile while generating / failed. --}}
<span class="ads-thumb {{ $ad->aspect->cssClass() }}">
    @if ($url)
        <button type="button" class="ads-thumb-button" data-ad-preview="{{ $ad->id }}" aria-label="{{ __('ai::ad_studio.preview.play', ['product' => $ad->product_name]) }}">
            <img src="{{ $url }}" alt="" loading="lazy" decoding="async" class="size-full object-contain">
            <span class="ads-play ads-play-sm" aria-hidden="true"><x-ui::icon name="ad-video" :size="14" /></span>
        </button>
    @else
        <span class="grid size-full place-items-center text-ink-muted" title="{{ __('ai::ad_studio.library.no_poster') }}"><x-ui::icon name="ad-video" :size="18" /></span>
    @endif
</span>
