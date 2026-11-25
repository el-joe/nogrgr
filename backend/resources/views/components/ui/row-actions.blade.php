{{--
    Row actions menu (Figma 33:10558): kebab → 200 wide menu. Use inside datatable
    action columns (rendered server-side by the DataTable class).
--}}
@props(['label' => null])
<x-ui::dropdown align="end" :width="200" :label="$label ?? __('ui.table.row_actions')" {{ $attributes }}>
    <x-slot:trigger>
        <x-ui::icon-button icon="more" :label="$label ?? __('ui.table.row_actions')" size="sm" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false" />
    </x-slot:trigger>
    {{ $slot }}
</x-ui::dropdown>
