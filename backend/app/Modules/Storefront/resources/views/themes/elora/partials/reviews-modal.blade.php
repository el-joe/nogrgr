{{--
    Elora customer reviews modal (Storefront additions 135:111928 / m 135:112101 bottom
    sheet) + write-a-review modal (135:112064), Elora mode: orange actions, gold stars.
    Same hooks as the shared reviews script (catalog/product.js): #sf-reviews,
    #sf-review-write, data-reviews-*, data-review-*.
--}}
@php($reviews = $page->reviews)
<x-sf::modal id="sf-reviews" :title="__st('product.reviews.title')" size="lg">
    <div class="grid gap-5" data-reviews-modal>
        <div class="flex flex-wrap items-center gap-x-8 gap-y-4">
            <div class="grid justify-items-center gap-1 max-sm:flex max-sm:items-center max-sm:gap-3">
                <p class="text-[44px] font-medium leading-none text-elo-ink max-sm:text-3xl" data-reviews-average>{{ number_format($reviews['average'], 1) }}</p>
                <x-sf::stars :rating="$reviews['average']" :size="18" class="!text-[#b47a00]" />
                <p class="text-xs text-sf-muted" data-reviews-total>{{ __st('product.reviews.count', ['count' => $reviews['count']]) }}</p>
            </div>
            <ul class="grid min-w-[240px] flex-1 gap-1.5 max-sm:hidden" role="list" data-reviews-distribution>
                @foreach ([5, 4, 3, 2, 1] as $star)
                    @php($share = $reviews['count'] > 0 ? (int) round(($reviews['distribution'][$star] ?? 0) / $reviews['count'] * 100) : 0)
                    <li class="flex items-center gap-2 text-xs text-sf-muted"><span class="w-6">{{ $star }}★</span><span class="elo-review-bar"><span data-width="{{ $share }}"></span></span><span class="w-8 text-end">{{ $share }}%</span></li>
                @endforeach
            </ul>
        </div>
        <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __st('product.reviews.filter') }}">
            <button type="button" class="elo-pill elo-pill-ghost h-9 text-sm" aria-pressed="true" data-review-filter="">{{ __st('product.reviews.all') }}</button>
            @foreach ([5, 4, 3, 2, 1] as $star)
                <button type="button" class="elo-pill elo-pill-ghost h-9 text-sm" aria-pressed="false" data-review-filter="{{ $star }}">{{ $star }} ★</button>
            @endforeach
        </div>
        <div class="flex items-center justify-between gap-3">
            <label class="inline-flex items-center gap-1 text-sm text-elo-ink">
                <span>{{ __st('product.reviews.sort') }}</span>
                <select class="bg-transparent font-medium outline-none" data-review-sort>
                    <option value="newest">{{ __st('product.reviews.sort_newest') }}</option>
                    <option value="highest">{{ __st('product.reviews.sort_highest') }}</option>
                    <option value="lowest">{{ __st('product.reviews.sort_lowest') }}</option>
                </select>
            </label>
            <button type="button" class="elo-btn elo-btn-primary mo-press h-9 min-h-9 px-4 text-xs max-sm:hidden" data-review-write><x-ui::icon name="edit" :size="16" />{{ __st('product.reviews.write') }}</button>
        </div>
        <div class="grid divide-y divide-elo-line border-t border-elo-line" data-reviews-list aria-live="polite"></div>
        <p class="py-6 text-center text-sm text-sf-muted" data-reviews-empty hidden>{{ __st('product.reviews.empty_filtered') }}</p>
        <p class="py-6 text-center text-sm text-sf-danger" data-reviews-error hidden>{{ __st('product.reviews.error') }}</p>
        <button type="button" class="elo-btn elo-btn-outline mo-press w-fit" data-reviews-more hidden>{{ __st('product.reviews.load_more') }}</button>
        <button type="button" class="elo-btn elo-btn-primary mo-press h-12 w-full sm:hidden" data-review-write>{{ __st('product.reviews.write') }}</button>
    </div>
    <template data-review-template>
        <article class="grid gap-2 py-4">
            <div class="flex items-center gap-2"><x-ui::icon name="user-circle" :size="22" class="text-sf-muted" /><span class="text-sm font-medium text-elo-ink" data-author></span><span class="rounded-full bg-sf-success-bg px-2 py-0.5 text-xs text-elo-green" data-verified>{{ __st('product.reviews.verified') }}</span><span class="ms-auto text-xs text-sf-muted" data-date></span></div>
            <span class="eco-stars eco-stars-dark" data-stars></span>
            <p class="text-sm font-medium text-elo-ink" data-review-title></p>
            <p class="whitespace-pre-line text-base text-elo-ink" data-body></p>
        </article>
    </template>
</x-sf::modal>

<x-sf::modal id="sf-review-write" :title="__st('product.reviews.write')" size="md">
    <form class="grid gap-5" data-review-form novalidate data-guest-text="{{ __st('product.reviews.sign_in_needed') }}" data-login-url="{{ Route::has('storefront.account.login') ? route('storefront.account.login', ['redirect' => '/'.request()->path()]) : '' }}">
        <div class="flex items-center gap-3">
            @if ($page->images)<img src="{{ $page->images[0] }}" alt="" width="48" height="48" class="size-12 rounded-md bg-elo-soft object-cover">@endif
            <p class="line-clamp-2 text-sm font-medium text-elo-ink">{{ $page->name }}</p>
        </div>
        <fieldset class="grid gap-2">
            <legend class="mb-2 text-sm font-medium text-elo-ink">{{ __st('product.reviews.your_rating') }}</legend>
            <div class="flex gap-1 text-elo-line" data-rating-input>
                @for ($star = 1; $star <= 5; $star++)
                    <label class="cursor-pointer transition-transform hover:scale-110 has-[:checked]:text-[#b47a00] has-[~label:hover]:text-[#b47a00]">
                        <input type="radio" name="rating" value="{{ $star }}" class="sr-only" required>
                        <svg width="34" height="34" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2.5l2.94 6.08 6.56.95-4.75 4.63 1.12 6.54L12 17.6l-5.87 3.1 1.12-6.54L2.5 9.53l6.56-.95L12 2.5z"/></svg>
                        <span class="sr-only">{{ __st('product.reviews.stars', ['count' => $star]) }}</span>
                    </label>
                @endfor
            </div>
            <p class="sf-error" data-error-for="rating" hidden></p>
        </fieldset>
        <div class="grid gap-2">
            <label for="elo-review-title" class="text-sm font-medium text-elo-ink">{{ __st('product.reviews.title_label') }}</label>
            <input id="elo-review-title" name="title" maxlength="120" class="sf-control">
            <p class="sf-error" data-error-for="title" hidden></p>
        </div>
        <div class="grid gap-2">
            <label for="elo-review-body" class="text-sm font-medium text-elo-ink">{{ __st('product.reviews.your_review') }}</label>
            <textarea id="elo-review-body" name="body" rows="5" minlength="3" maxlength="2000" required class="sf-control min-h-[120px] py-3" placeholder="{{ __st('product.reviews.placeholder') }}"></textarea>
            <p class="sf-error" data-error-for="body" hidden></p>
        </div>
        <p class="text-xs text-sf-muted">{{ __st('product.reviews.moderation') }}</p>
        <p class="rounded-lg bg-sf-danger-bg px-3 py-2 text-sm text-sf-danger" data-review-form-error hidden></p>
        <button type="submit" class="elo-btn elo-btn-primary mo-press h-12 w-full">{{ __st('product.reviews.submit') }}</button>
    </form>
</x-sf::modal>
