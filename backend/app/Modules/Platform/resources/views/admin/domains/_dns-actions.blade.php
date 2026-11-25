{{-- DNS record templates › row actions: edit (opens the record modal) and delete. --}}
@if (auth('admin')->user()?->can('vendors.domains.manage'))
    @php
        $record = [
            'id' => $template->id,
            'type' => $template->type->value,
            'host' => $template->host,
            'value' => $template->value,
            'ttl' => $template->ttl,
            'priority' => $template->priority,
            'sort_order' => $template->sort_order,
            'url' => route('admin.vendors.domains.dns.update', $template->id),
        ];
        $name = $template->type->label().' '.$template->host;
    @endphp
    <div class="flex items-center justify-end gap-1">
        <x-ui::icon-button icon="edit" size="sm" :label="__('platform::domains.dns.edit_named', ['record' => $name])" data-dns-edit="{{ json_encode($record) }}" />
        <x-ui::row-actions :label="__('platform::domains.dns.more', ['record' => $name])">
            <x-ui::dropdown-item icon="edit" data-dns-edit="{{ json_encode($record) }}">{{ __('platform::domains.dns.edit') }}</x-ui::dropdown-item>
            <x-ui::dropdown-divider />
            <x-ui::dropdown-item
                icon="trash"
                tone="danger"
                :data-confirm="__('platform::domains.dns.delete_message')"
                :data-confirm-title="__('platform::domains.dns.delete_title', ['record' => $name])"
                :data-confirm-label="__('platform::domains.dns.delete')"
                data-confirm-method="DELETE"
                :data-confirm-url="route('admin.vendors.domains.dns.destroy', $template->id)"
            >{{ __('platform::domains.dns.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    </div>
@endif
