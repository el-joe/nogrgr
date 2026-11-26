{{--
    Write a review from an order (SF-ECO-028d, Figma Storefront additions 103:107273, m
    sheets 103:107488): item (choice when several lines can be reviewed), 5 stars, comment.
    Opener: data-review='{"url": …/items/__ITEM__/review, "items": [{id, name, image, variant}]}'.
    Posts to the Store API (Engagement ReviewController::storeForItem).
--}}
<x-sf::modal id="sf-write-review" :title="__st('orders.review_title')" size="sm" data-review-modal>
    <form method="POST" class="grid gap-4" data-ajax data-review-form novalidate>
        <div class="grid gap-2" data-review-items></div>
        <fieldset class="grid gap-2">
            <legend class="sf-label mb-1.5">{{ __st('orders.rating_label') }}<span class="sf-required" aria-hidden="true">*</span></legend>
            <div class="flex gap-1.5" data-review-stars>
                @foreach (range(1, 5) as $star)
                    <label class="cursor-pointer rounded text-[#c4c4c4] transition-transform duration-150 hover:scale-110 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-sf-ink data-[on]:text-[#ffb00a] data-[on]:[&_svg]:fill-current" data-star="{{ $star }}">
                        <input type="radio" name="rating" value="{{ $star }}" class="sr-only">
                        <x-ui::icon name="star" :size="28" />
                        <span class="sr-only">{{ __st('orders.star', ['count' => $star]) }}</span>
                    </label>
                @endforeach
            </div>
            <p class="sf-error" data-error-for="rating" hidden></p>
        </fieldset>
        <x-sf::form.field :label="__st('orders.review_label')" for="sf-review-body" required error-key="body">
            <textarea id="sf-review-body" name="body" class="sf-control text-sm min-h-24 py-2.5" minlength="3" maxlength="2000" required placeholder="{{ __st('orders.review_placeholder') }}"></textarea>
        </x-sf::form.field>
        <p class="text-xs text-sf-muted">{{ __st('orders.review_hint') }}</p>
        <x-sf::form.button type="submit" block :busy="__st('orders.submitting')">{{ __st('orders.submit_review') }}</x-sf::form.button>
    </form>
    <template data-review-item-template>
        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-transparent p-1 has-[:checked]:border-sf-border">
            <input type="radio" name="review_item" class="sf-radio" data-review-item-input>
            <span class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-[#f5f5f5]"><img alt="" width="48" height="48" class="size-full object-contain bg-media" data-review-item-image></span>
            <span class="grid min-w-0 gap-0.5"><span class="truncate text-sm font-medium text-sf-ink" data-review-item-name></span><span class="text-xs text-sf-muted" data-review-item-variant></span></span>
        </label>
    </template>
</x-sf::modal>
<template data-i18n-reorder>
    <span data-text="done">{{ __st('orders.reorder_done') }}</span>
    <span data-text="partial">{{ __st('orders.reorder_partial', ['items' => ':items']) }}</span>
    <span data-text="failed">{{ __st('orders.reorder_failed') }}</span>
</template>
