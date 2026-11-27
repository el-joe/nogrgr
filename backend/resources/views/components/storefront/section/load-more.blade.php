{{-- "See More ⌄" (Ecommet 1:1100): a real link to the next page, enhanced to append the next cards in place. --}}
@props(['url' => null, 'target'])
@if ($url)
    <div class="flex justify-center">
        <a href="{{ $url }}" class="eco-pill-dark eco-see-more mo-press" data-load-more data-target="{{ $target }}" data-loading-text="{{ __st('catalog.loading') }}" data-error-text="{{ __st('catalog.load_error') }}" rel="next">
            <span data-load-more-label>{{ __st('catalog.see_more') }}</span><x-ui::icon name="arrow-long-down" :size="24" class="eco-see-more-icon" />
        </a>
    </div>
@endif
