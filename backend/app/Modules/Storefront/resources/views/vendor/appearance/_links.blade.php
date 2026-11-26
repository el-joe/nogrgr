{{--
    Appearance › Header links (VEN-034d) / footer links (VEN-034e), Figma 85:107559: drag
    handle + position, label, address, on / off switch, ⋯ (edit / delete); add in a modal.
    $placement = header | footer, $links = StoreMenuLink rows in order.
--}}
@php
    $isHeader = $placement === 'header';
    $createUrl = vendor_route('store.appearance.links.create', ['placement' => $placement]);
@endphp
<x-ui::card :title="__($isHeader ? 'content::store_appearance.links.header_title' : 'content::store_appearance.links.footer_title')" :subtitle="__($isHeader ? 'content::store_appearance.links.header_help' : 'content::store_appearance.links.footer_help', ['max' => $maxHeaderLinks])">
    @if ($canManage)
        <x-slot:actions>
            <x-ui::button size="sm" icon="plus" data-remote-modal="appearance-modal" :data-url="$createUrl" :disabled="$isHeader && $links->count() >= $maxHeaderLinks">{{ __('content::store_appearance.links.add') }}</x-ui::button>
        </x-slot:actions>
    @endif
    @if ($links->isEmpty())
        <x-ui::empty-state compact icon="link" :title="__($isHeader ? 'content::store_appearance.links.empty_header' : 'content::store_appearance.links.empty_footer')">
            @if ($canManage)
                <x-ui::button size="sm" icon="plus" data-remote-modal="appearance-modal" :data-url="$createUrl">{{ __('content::store_appearance.links.add') }}</x-ui::button>
            @endif
        </x-ui::empty-state>
    @else
        <ul class="grid gap-3" role="list" @if ($canManage) data-link-sort="{{ vendor_route('store.appearance.links.sort') }}" data-placement="{{ $placement }}" @endif>
            @foreach ($links as $link)
                @php($label = (string) $link->label)
                <li class="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3" data-id="{{ $link->id }}">
                    @if ($canManage)
                        <button type="button" class="grid size-8 shrink-0 cursor-grab place-items-center rounded-control text-ink-muted hover:bg-subtle hover:text-ink active:cursor-grabbing" data-drag-handle aria-label="{{ __('content::store_appearance.links.drag', ['label' => $label]) }}" title="{{ __('content::store_appearance.links.drag', ['label' => $label]) }}">
                            <x-ui::icon name="menu" :size="16" />
                        </button>
                    @endif
                    <span class="w-4 shrink-0 text-center text-xs text-ink-secondary" data-sort-position>{{ $loop->iteration }}</span>
                    <span class="grid min-w-0 flex-1 gap-0.5">
                        <span class="flex items-center gap-2 truncate font-medium text-ink">
                            {{ $label }}
                            @if (! $isHeader && $link->footer_column)
                                <x-ui::badge>{{ $link->footer_column->label() }}</x-ui::badge>
                            @endif
                        </span>
                        <span class="flex items-center gap-1 truncate text-xs text-ink-secondary" dir="ltr">
                            @if ($link->isExternal())<x-ui::icon name="link" :size="12" :label="__('content::store_appearance.links.external')" />@endif{{ $link->url }}
                        </span>
                    </span>
                    @if ($canManage)
                        <x-ui::switch :name="'link_active_'.$link->id" :id="'link-active-'.$link->id" :checked="$link->is_active" :url="vendor_route('store.appearance.links.toggle', ['link' => $link->id])" field="is_active" :aria-label="__('content::store_appearance.links.show_named', ['label' => $label])" />
                        <x-ui::row-actions>
                            <x-ui::dropdown-item icon="edit" data-remote-modal="appearance-modal" :data-url="vendor_route('store.appearance.links.edit', ['link' => $link->id])">{{ __('content::store_appearance.links.edit') }}</x-ui::dropdown-item>
                            <x-ui::dropdown-divider />
                            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('content::store_appearance.links.delete_message')" :data-confirm-title="__('content::store_appearance.links.delete_title', ['label' => $label])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('store.appearance.links.destroy', ['link' => $link->id])">{{ __('content::store_appearance.links.delete') }}</x-ui::dropdown-item>
                        </x-ui::row-actions>
                    @else
                        <x-ui::status :status="$link->is_active ? 'active' : 'inactive'">{{ __($link->is_active ? 'content::store_appearance.links.active' : 'ui.status.inactive') }}</x-ui::status>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-ui::card>
