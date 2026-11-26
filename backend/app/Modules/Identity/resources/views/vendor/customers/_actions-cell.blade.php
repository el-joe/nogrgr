{{-- Row actions of VEN-025: view; with customers.manage also edit (modal), activate / deactivate and delete. --}}
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :label="__('identity::customers.view_named', ['name' => $customer->name])" :href="vendor_route('sales.customers.show', ['customer' => $customer->id])" />
    @if ($canManage)
        <x-ui::icon-button icon="edit" size="sm" :label="__('identity::customers.edit_named', ['name' => $customer->name])" data-remote-modal="vendor-customer-modal" :data-url="vendor_route('sales.customers.edit', ['customer' => $customer->id])" />
        <x-ui::row-actions>
            @php $active = $customer->status === \App\Modules\Identity\Enums\AccountStatus::Active; @endphp
            <x-ui::dropdown-item :icon="$active ? 'x-circle' : 'check-circle'" :data-confirm="__($active ? 'identity::customers.deactivate_message' : 'identity::customers.activate_message')" :data-confirm-title="$customer->name" data-confirm-tone="primary" :data-confirm-label="__($active ? 'identity::customers.deactivate' : 'identity::customers.activate')" data-confirm-method="PATCH" :data-confirm-url="vendor_route('sales.customers.toggle-active', ['customer' => $customer->id])">{{ __($active ? 'identity::customers.deactivate' : 'identity::customers.activate') }}</x-ui::dropdown-item>
            <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('identity::customers.delete_message')" :data-confirm-title="__('identity::customers.delete_title', ['name' => $customer->name])" data-confirm-method="DELETE" :data-confirm-url="vendor_route('sales.customers.destroy', ['customer' => $customer->id])">{{ __('identity::customers.delete') }}</x-ui::dropdown-item>
        </x-ui::row-actions>
    @endif
</div>
