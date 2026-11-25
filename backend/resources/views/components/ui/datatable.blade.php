{{--
    Data table (Figma 34:11868) around server-side Yajra DataTables
    (resources/js/panels/datatable.js). Card (radius 16) with the filters bar slot,
    header row on bg/subtle, 56px rows, hover bg/subtle, selected rows brand-subtle,
    "Showing x–y of N" + per-page select + pagination, skeleton rows while loading,
    "No data" / "No results" / error states, and the floating bulk action bar.

    <x-ui::datatable id="products" :url="route('admin.catalog.products.data')" selectable
        :columns="[
            ['data' => 'name', 'title' => __('…')],
            ['data' => 'price', 'title' => __('…'), 'align' => 'end'],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]">
        <x-slot:filters><x-ui::filters-bar target="products">…</x-ui::filters-bar></x-slot:filters>
        <x-slot:bulk><x-ui::button size="sm" variant="ghost" data-bulk-action data-url="…" data-method="PATCH">…</x-ui::button></x-slot:bulk>
    </x-ui::datatable>
--}}
@props([
    'id',
    'url',
    'columns' => [],
    'selectable' => false,
    'rowKey' => 'id',
    'pageLength' => 10,
    'perPage' => [10, 25, 50, 100],
    'order' => null,
    'emptyTitle' => null,
    'emptyDescription' => null,
    'stack' => true,
])
@php
    $jsColumns = collect($columns)->map(fn ($column) => array_filter([
        'data' => $column['data'],
        'name' => $column['name'] ?? $column['data'],
        'orderable' => $column['orderable'] ?? true,
        'searchable' => $column['searchable'] ?? true,
        'align' => $column['align'] ?? null,
        'className' => $column['className'] ?? null,
    ], fn ($value) => $value !== null))->values()->all();
    $skeletonColumns = count($columns) + ($selectable ? 1 : 0);
@endphp
<div
    data-datatable-card
    data-state="loading"
    @if ($stack) data-stack @endif
    {{ $attributes->class(['ui-datatable relative rounded-card border border-line bg-surface shadow-card']) }}
>
    @isset($filters)
        <div class="border-b border-line p-4">{{ $filters }}</div>
    @endisset

    <div class="overflow-x-auto overflow-y-hidden">
        <table
            id="{{ $id }}"
            data-datatable
            data-url="{{ $url }}"
            data-table-columns='@json($jsColumns)'
            data-table-length="{{ $pageLength }}"
            data-row-key="{{ $rowKey }}"
            @if ($selectable) data-selectable @endif
            @if ($order) data-table-order='@json($order)' @endif
            class="w-full"
        >
            <thead>
                <tr>
                    @if ($selectable)
                        <th scope="col" class="w-12">
                            <input type="checkbox" class="ui-check" data-dt-select-all aria-label="{{ __('ui.table.select_all') }}">
                        </th>
                    @endif
                    @foreach ($columns as $column)
                        <th scope="col" @if (($column['align'] ?? null) === 'end') data-align="end" @endif>
                            @if (! empty($column['title']))
                                {{ $column['title'] }}
                            @else
                                <span class="sr-only">{{ __('ui.table.actions') }}</span>
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
        </table>
        <div data-dt-skeleton aria-hidden="true">
            @for ($row = 0; $row < 5; $row++)
                <div class="flex h-14 items-center gap-4 border-b border-line px-4 last:border-b-0">
                    @for ($cell = 0; $cell < $skeletonColumns; $cell++)
                        <span class="ui-skeleton h-3 flex-1" @style(['max-width: 18px' => $selectable && $cell === 0])></span>
                    @endfor
                </div>
            @endfor
            <p class="sr-only" role="status">{{ __('ui.table.loading') }}</p>
        </div>
    </div>

    <template data-dt-empty>
        <x-ui::empty-state variant="no-data" :title="$emptyTitle" :description="$emptyDescription">{{ $empty ?? '' }}</x-ui::empty-state>
    </template>
    <template data-dt-empty-filtered>
        <x-ui::empty-state variant="no-results">
            <x-ui::button variant="secondary" :data-filters-clear="$id">{{ __('ui.filters.clear') }}</x-ui::button>
        </x-ui::empty-state>
    </template>
    <template data-dt-error>
        <x-ui::empty-state variant="error">
            <x-ui::button variant="secondary" icon="refresh" data-dt-retry>{{ __('ui.retry') }}</x-ui::button>
        </x-ui::empty-state>
    </template>

    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3">
        <div class="flex items-center gap-3 text-[13px] text-ink-secondary">
            <p data-dt-info aria-live="polite"></p>
            <label class="sr-only" for="{{ $id }}-length">{{ __('ui.table.per_page') }}</label>
            <select id="{{ $id }}-length" data-dt-length class="h-8 rounded-control border border-line-strong bg-field px-2 text-[13px] text-ink">
                @foreach ($perPage as $size)
                    <option value="{{ $size }}" @selected($size === $pageLength)>{{ __('ui.table.per_page_option', ['count' => $size]) }}</option>
                @endforeach
            </select>
        </div>
        <nav data-dt-pagination class="flex items-center gap-1" aria-label="{{ __('ui.table.pagination') }}"></nav>
    </footer>

    @isset($bulk)
        <div data-dt-bulk hidden class="fixed inset-x-4 bottom-6 z-30 mx-auto flex w-fit max-w-[calc(100vw-32px)] flex-wrap items-center gap-2 rounded-control bg-inverse px-3 py-2 text-[13px] text-on-inverse shadow-overlay" role="region" aria-label="{{ __('ui.table.bulk_actions') }}">
            <span class="flex items-center gap-2 pe-2">
                <span data-dt-bulk-count class="grid h-5 min-w-5 place-items-center rounded-full bg-primary px-1.5 text-xs font-semibold text-on-primary">0</span>
                {{ __('ui.table.selected') }}
            </span>
            <span class="h-5 w-px bg-on-inverse/20" aria-hidden="true"></span>
            <div class="flex flex-wrap items-center gap-1 [&_.ui-btn-ghost]:text-on-inverse [&_.ui-btn-ghost:hover]:bg-white/10 [&_.ui-btn-danger]:bg-transparent [&_.ui-btn-danger]:text-[#ff8a8a]">{{ $bulk }}</div>
            <button type="button" data-dt-bulk-clear class="ms-auto rounded-chip px-2 py-1 font-medium hover:bg-white/10">{{ __('ui.table.clear') }}</button>
        </div>
    @endisset
</div>
