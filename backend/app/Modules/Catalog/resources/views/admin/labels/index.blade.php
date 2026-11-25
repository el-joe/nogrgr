{{--
    Admin › Catalog › Labels (ADM-016, Figma Admin Panel 65:27123): labels sorted by drag
    and drop (saved at once), active switch, edit / create in a modal (ADM-016a) and
    delete, plus a shopper preview of the active labels with sample placeholder values.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('catalog.labels.manage');
    $active = $labels->where('is_active', true);
@endphp
<x-layouts.panel surface="admin" :title="__('catalog::labels.title')" data-page="admin-catalog">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('catalog::labels.title')"
            :subtitle="__('catalog::labels.subtitle')"
            :breadcrumbs="[__('navigation.sections.catalog') => null, __('navigation.admin.labels_badges') => null, __('catalog::labels.title') => null]"
        >
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="label-modal" :data-url="route('admin.catalog.labels.create')">{{ __('catalog::labels.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <x-ui::card :title="__('catalog::labels.list')">
                @if ($labels->isEmpty())
                    <x-ui::empty-state icon="tag" :title="__('catalog::labels.empty_title')" :description="__('catalog::labels.empty_description')">
                        @if ($canManage)
                            <x-ui::button icon="plus" data-remote-modal="label-modal" :data-url="route('admin.catalog.labels.create')">{{ __('catalog::labels.add') }}</x-ui::button>
                        @endif
                    </x-ui::empty-state>
                @else
                    <ol @if ($canManage) data-sortable-list data-sort-url="{{ route('admin.catalog.labels.sort') }}" data-sort-autosave @endif class="grid gap-2 aria-busy:opacity-60">
                        @foreach ($labels as $label)
                            @php
                                $where = $label->country_ids === null || $label->country_ids === []
                                    ? __('catalog::labels.all_countries')
                                    : collect($label->country_ids)->map(fn ($id) => $countries[$id] ?? null)->filter()->implode(', ');
                            @endphp
                            <li data-sortable-item data-id="{{ $label->id }}" class="flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2.5 transition-colors focus-within:border-primary hover:bg-subtle">
                                @if ($canManage)
                                    <button type="button" data-drag-handle class="grid size-7 shrink-0 cursor-grab place-items-center rounded-chip text-ink-muted hover:bg-surface hover:text-ink focus-visible:outline-2 focus-visible:outline-primary" aria-label="{{ __('catalog::common.drag_handle', ['name' => $label->text]) }}" title="{{ __('catalog::common.drag_hint') }}">
                                        <x-ui::icon name="menu" :size="16" />
                                    </button>
                                @endif
                                <span data-position class="w-5 shrink-0 text-center text-xs text-ink-secondary" dir="ltr">{{ $loop->iteration }}</span>
                                @if ($label->icon)
                                    <x-ui::icon :name="$label->icon" :size="18" class="shrink-0 text-ink-secondary" />
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-ink">{{ $label->text }}</span>
                                    <span class="block truncate text-xs text-ink-secondary">{{ $label->placeholders() !== [] ? __('catalog::labels.kind_placeholder') : __('catalog::labels.kind_static') }} · {{ $where }}</span>
                                </span>
                                <x-ui::status :status="$label->is_active ? 'active' : 'inactive'" class="hidden sm:inline-flex" />
                                @if ($canManage)
                                    <x-ui::switch :name="'is_active_'.$label->id" field="is_active" :url="route('admin.catalog.labels.toggle', $label)" :checked="$label->is_active" :aria-label="__('catalog::labels.toggle', ['name' => $label->text])" />
                                    <x-ui::row-actions>
                                        <x-ui::dropdown-item icon="edit" data-remote-modal="label-modal" :data-url="route('admin.catalog.labels.edit', $label)">{{ __('catalog::common.edit') }}</x-ui::dropdown-item>
                                        <x-ui::dropdown-divider />
                                        <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('catalog::labels.delete_message', ['name' => $label->text])" :data-confirm-title="__('catalog::labels.delete_title')" data-confirm-method="DELETE" :data-confirm-url="route('admin.catalog.labels.destroy', $label)">{{ __('catalog::common.delete') }}</x-ui::dropdown-item>
                                    </x-ui::row-actions>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui::card>

            <x-ui::card :title="__('catalog::labels.preview')" :subtitle="__('catalog::labels.preview_subtitle')">
                <div class="grid gap-3">
                    @include('catalog::admin.partials.thumb', ['url' => null, 'size' => 'aspect-[2/1] w-full'])
                    <p class="text-sm font-medium text-ink">{{ __('catalog::labels.preview_product') }}</p>
                    @forelse ($active as $label)
                        <p class="flex items-center gap-2 rounded-control bg-success-bg px-3 py-2.5 text-[13px] text-success">
                            <x-ui::icon :name="$label->icon ?? 'check-circle'" :size="16" class="shrink-0" />
                            <span>{{ $renderer->render($label) }}</span>
                        </p>
                    @empty
                        <p class="rounded-control bg-subtle px-3 py-4 text-center text-[13px] text-ink-secondary">{{ __('catalog::labels.preview_empty') }}</p>
                    @endforelse
                </div>
            </x-ui::card>
        </div>
    </div>

    @if ($canManage)
        <x-ui::modal id="label-modal" :title="__('catalog::labels.create_title')" size="md">
            @include('catalog::admin.partials.remote-states', ['error' => __('catalog::common.load_error')])
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::common.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="label-form" icon="check" data-remote-submit>{{ __('catalog::common.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
