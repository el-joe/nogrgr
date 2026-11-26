{{--
    Header search with autocomplete (Ecommet 1:1163): recent searches (kept in this browser)
    on focus, product / category suggestions while typing, Enter → search results.
    Only rendered when the storefront search route exists.
--}}
@props(['variant' => 'ecommet'])
@if (Route::has('storefront.search'))
    @php($id = 'sf-search-'.$variant)
    <form method="GET" action="{{ route('storefront.search') }}" role="search" data-search {{ $attributes->class(['relative']) }}>
        <label class="sr-only" for="{{ $id }}">{{ __st('shell.search') }}</label>
        @if ($variant === 'ecommet')
            <div class="eco-search">
                <input id="{{ $id }}" type="search" name="q" value="{{ request()->routeIs('storefront.search') ? request()->query('q') : '' }}" placeholder="{{ __st('shell.search') }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-panel" data-search-input>
                <button type="submit" class="eco-search-btn mo-press" aria-label="{{ __st('shell.search_submit') }}"><x-ui::icon name="search" :size="16" /></button>
            </div>
        @elseif ($variant === 'ecommet-mobile')
            <div class="flex h-8 items-center gap-2 rounded-full bg-[#f1f1f1] px-3">
                <input id="{{ $id }}" type="search" name="q" value="{{ request()->routeIs('storefront.search') ? request()->query('q') : '' }}" placeholder="{{ __st('shell.search') }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-panel" class="min-w-0 flex-1 bg-transparent text-xs tracking-[0.5px] text-eco-black outline-none placeholder:text-eco-gray" data-search-input>
            </div>
        @elseif (str_starts_with($variant, 'elora'))
            {{-- Elora search pill (1:20867: 54 tall, #F4F4F4; phone 1:21125: white pill on the orange header). --}}
            <div @class(['flex items-center gap-3 rounded-full px-5', 'h-[54px] bg-[#f4f4f4]' => $variant === 'elora', 'h-12 bg-white shadow-[0_4px_7px_rgba(0,0,0,0.1)]' => $variant === 'elora-mobile'])>
                <x-ui::icon name="search" :size="22" class="shrink-0 text-sf-ink" />
                <input id="{{ $id }}" type="search" name="q" value="{{ request()->routeIs('storefront.search') ? request()->query('q') : '' }}" placeholder="{{ __st('shell.search') }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $id }}-panel" class="min-w-0 flex-1 bg-transparent text-base text-sf-ink outline-none placeholder:text-[#8f8f8f]" data-search-input>
            </div>
        @else
            <div class="flex h-[54px] items-center gap-3 rounded-full bg-[#f1f1f1] px-5">
                <x-ui::icon name="search" :size="22" class="text-sf-ink" />
                <input id="{{ $id }}" type="search" name="q" placeholder="{{ __st('shell.search') }}" class="min-w-0 flex-1 bg-transparent text-base text-sf-ink outline-none">
            </div>
        @endif
        @if (str_starts_with($variant, 'ecommet') || str_starts_with($variant, 'elora'))
        <div id="{{ $id }}-panel" class="{{ str_starts_with($variant, 'elora') ? 'elo-dropdown' : 'eco-dropdown eco-dropdown-arrow' }} inset-x-0 top-[calc(100%+12px)] min-w-[280px] p-4 text-start [--arrow-x:calc(50%-8px)] max-lg:fixed max-lg:inset-x-3 max-lg:top-[68px]" role="listbox" aria-label="{{ __st('search.suggestions') }}" data-search-panel>
            <div data-search-recent hidden>
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-base font-medium">{{ __st('search.recent') }}</p>
                    <button type="button" class="grid size-7 place-items-center rounded-full text-eco-gray hover:bg-black/5" aria-label="{{ __st('search.clear_recent') }}" data-search-clear><x-ui::icon name="trash" :size="18" /></button>
                </div>
                <div class="flex flex-wrap gap-2" data-search-recent-list></div>
            </div>
            <div data-search-results hidden></div>
            <p class="text-sm text-eco-gray" data-search-empty hidden>{{ __st('search.no_suggestions') }}</p>
            <div class="grid gap-2" data-search-loading hidden aria-hidden="true">
                <span class="eco-skeleton h-10"></span><span class="eco-skeleton h-10"></span><span class="eco-skeleton h-10"></span>
            </div>
        </div>
        <template data-search-templates
            data-text-products="{{ __st('search.products') }}"
            data-text-categories="{{ __st('search.categories') }}"
            data-text-all="{{ __st('search.see_all') }}"></template>
        @endif
    </form>
@endif
