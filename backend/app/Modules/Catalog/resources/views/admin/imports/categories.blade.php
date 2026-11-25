{{--
    Admin › Catalog › Products › Import › Map categories (ADM-013 "Map categories" step):
    every source category seen by previews / runs with the central category it feeds.
    Unmapped categories make their products skipped. Filter all / unmapped only.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('catalog.imports.manage');
    $tabs = [
        'all' => ['label' => __('catalog::imports.mapping.filter_all'), 'count' => number_format($overview['mappings']), 'href' => route('admin.catalog.imports.categories', ['driver' => $driver->value])],
        'unmapped' => ['label' => __('catalog::imports.mapping.filter_unmapped'), 'count' => number_format($overview['unmapped']), 'href' => route('admin.catalog.imports.categories', ['driver' => $driver->value, 'unmapped' => 1])],
    ];
@endphp
<x-layouts.panel surface="admin" :title="__('catalog::imports.mapping.title')" data-page="admin-catalog-import">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('catalog::imports.mapping.title')"
            :subtitle="__('catalog::imports.mapping.subtitle', ['driver' => $driver->label()])"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('catalog::products.title') => route('admin.catalog.products.index'), __('catalog::imports.breadcrumb') => route('admin.catalog.imports.index', ['driver' => $driver->value]), __('catalog::imports.map_categories') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="chevron-left" class="rtl:[&_svg]:rotate-180" :href="route('admin.catalog.imports.index', ['driver' => $driver->value])">{{ __('catalog::imports.mapping.back') }}</x-ui::button>
                @if ($canManage && $rows->isNotEmpty())
                    <x-ui::button type="submit" form="category-mapping-form" icon="check">{{ __('catalog::imports.mapping.save') }}</x-ui::button>
                @endif
            </x-slot:actions>
            <x-slot:tabs>
                <x-ui::tabs id="mapping-tabs" variant="segmented" :active="$unmappedOnly ? 'unmapped' : 'all'" :label="__('catalog::imports.mapping.title')" :items="$tabs" />
            </x-slot:tabs>
        </x-ui::page-header>

        <x-ui::card :padding="false" class="overflow-hidden">
            @if ($rows->isEmpty())
                <div class="p-5">
                    @if ($unmappedOnly && $overview['mappings'] > 0)
                        <x-ui::empty-state icon="check-circle" :title="__('catalog::imports.mapping.all_mapped_title')" :description="__('catalog::imports.mapping.all_mapped_description')" />
                    @else
                        <x-ui::empty-state icon="layers" :title="__('catalog::imports.mapping.empty_title')" :description="__('catalog::imports.mapping.empty_description')">
                            <x-ui::button variant="secondary" :href="route('admin.catalog.imports.index', ['driver' => $driver->value])">{{ __('catalog::imports.mapping.back') }}</x-ui::button>
                        </x-ui::empty-state>
                    @endif
                </div>
            @else
                <form id="category-mapping-form" method="POST" action="{{ route('admin.catalog.imports.categories.save') }}" data-ajax data-unsaved-form novalidate>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="driver" value="{{ $driver->value }}">
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3 text-[13px] text-ink-secondary">
                        <span>{{ __('catalog::imports.mapping.count', ['mapped' => number_format($overview['mapped']), 'total' => number_format($overview['mappings'])]) }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="ui-table w-full text-sm">
                            <thead>
                                <tr class="border-b border-line bg-subtle text-xs font-medium text-ink-secondary">
                                    <th scope="col" class="px-5 py-3 text-start">{{ __('catalog::imports.mapping.columns.source') }}</th>
                                    <th scope="col" class="px-5 py-3 text-start">{{ __('catalog::imports.mapping.columns.external_id') }}</th>
                                    <th scope="col" class="w-[45%] px-5 py-3 text-start">{{ __('catalog::imports.mapping.columns.category') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr class="border-b border-line last:border-b-0">
                                        <td class="px-5 py-3">
                                            <span class="flex items-center gap-2 font-medium text-ink">
                                                @if ($row->category === null)
                                                    <span class="size-2 shrink-0 rounded-full bg-warning" aria-hidden="true"></span>
                                                @endif
                                                {{ $row->label() }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3"><bdi dir="ltr" class="tabular-nums text-ink-secondary">{{ $row->external_id }}</bdi></td>
                                        <td class="px-5 py-3">
                                            <x-ui::select :name="'mappings['.$row->id.']'" :label="__('catalog::imports.mapping.columns.category').' — '.$row->label()" label-hidden :options="$categoryOptions" :selected="$row->category?->id" :placeholder="__('catalog::imports.mapping.placeholder')" allow-clear :disabled="! $canManage" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
            @endif
        </x-ui::card>
    </div>
</x-layouts.panel>
