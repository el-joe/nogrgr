{{--
    Filters bar (Figma 33:10628): debounced search (300ms) + quick filters (slot, 40h) +
    "More filters" (opens a Drawer with the `more` slot) + optional column chooser.
    Active filters show as chips with "Clear all". Everything is synced to the URL.
    chips=false hides the active-filter chips (e.g. a lone period picker).
    target = datatable id; reload=true instead reloads the page with the new query
    (pages without a table, e.g. the dashboard date range).
    Quick filter fields need data-filter (+ data-filter-label for the chip).
--}}
@props(['target' => '', 'search' => true, 'searchPlaceholder' => null, 'columns' => [], 'reload' => false, 'chips' => true])
@php
    $drawerId = ($target ?: 'page').'-filters';
@endphp
<div data-filters="{{ $target }}" @if ($reload) data-filters-reload @endif @unless ($chips) data-filters-chips="false" @endunless {{ $attributes->class(['ui-filters grid gap-3']) }}>
    <div class="flex flex-wrap items-center gap-2">
        @if ($search)
            <div class="ui-control min-h-10 w-full sm:w-[280px]">
                <x-ui::icon name="search" :size="18" class="text-ink-muted" />
                <label for="{{ $drawerId }}-search" class="sr-only">{{ __('ui.filters.search') }}</label>
                <input id="{{ $drawerId }}-search" type="search" name="search" data-filter data-filter-debounce placeholder="{{ $searchPlaceholder ?? __('ui.filters.search') }}" class="h-9" autocomplete="off">
            </div>
        @endif
        {{ $slot }}
        @isset($more)
            <x-ui::button variant="ghost" icon="filter" data-overlay-open="{{ $drawerId }}" aria-haspopup="dialog">
                {{ __('ui.filters.more') }}
                <span data-filters-count="{{ $target }}" class="ms-1 grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1.5 text-xs font-semibold text-on-primary" hidden>0</span>
            </x-ui::button>
        @endisset
        <div class="ms-auto flex items-center gap-2">
            {{ $end ?? '' }}
            @if ($columns)
                <x-ui::dropdown align="end" :width="220" :label="__('ui.filters.columns')">
                    <x-slot:trigger>
                        <x-ui::icon-button icon="layers" variant="secondary" :label="__('ui.filters.columns')" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" />
                    </x-slot:trigger>
                    <p class="px-2.5 pb-2 pt-1 text-xs font-semibold uppercase tracking-[0.06em] text-ink-muted">{{ __('ui.filters.columns') }}</p>
                    @foreach ($columns as $index => $title)
                        <label class="ui-menu-item cursor-pointer">
                            <input type="checkbox" class="ui-check" data-dt-column="{{ $index }}" checked>
                            {{ $title }}
                        </label>
                    @endforeach
                </x-ui::dropdown>
            @endif
        </div>
    </div>
    <div data-filter-chips class="flex flex-wrap items-center gap-2" hidden>
        <button type="button" data-filters-clear="{{ $target }}" class="text-xs font-medium text-ink-secondary hover:text-ink hover:underline">{{ __('ui.filters.clear_all') }}</button>
    </div>
</div>
@isset($more)
    <x-ui::drawer :id="$drawerId" :title="__('ui.filters.title')" :subtitle="$moreSubtitle ?? null">
        <div class="grid gap-5" data-filter-for="{{ $target }}" data-filter-defer>
            {{ $more }}
        </div>
        <x-slot:footer>
            <x-ui::button variant="ghost" :data-filters-clear="$target">{{ __('ui.filters.reset') }}</x-ui::button>
            <x-ui::button :data-filters-apply="$target" data-overlay-close>{{ __('ui.filters.apply') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::drawer>
@endisset
