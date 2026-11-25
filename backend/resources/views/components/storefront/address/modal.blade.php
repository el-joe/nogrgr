{{-- The canonical address modal / bottom sheet (Figma Storefront additions §1–2). --}}
@props(['id' => 'sf-address-modal', 'countries' => [], 'phoneCountry' => null, 'urls' => []])
<x-sf::modal :id="$id" :title="__('identity::addresses.new_title')" size="md" data-address-modal
    data-title-new="{{ __('identity::addresses.new_title') }}" data-title-edit="{{ __('identity::addresses.edit_title') }}"
    data-title-shipping-new="{{ __('identity::addresses.shipping_add') }}" data-title-shipping-edit="{{ __('identity::addresses.shipping_edit') }}"
    data-title-billing-new="{{ __('identity::addresses.billing_add') }}" data-title-billing-edit="{{ __('identity::addresses.billing_edit') }}">
    <x-sf::address.form :id="$id.'-form'" :countries="$countries" :phone-country="$phoneCountry" :urls="$urls" />
</x-sf::modal>
<x-sf::modal id="{{ $id }}-delete" :title="__('storefront::account.delete_address_title')" size="sm" data-address-delete-modal>
    <p class="text-sm text-sf-muted" data-delete-message data-template="{{ __('storefront::account.delete_address_message', ['address' => '__ADDRESS__']) }}"></p>
    <div class="mt-5 grid grid-cols-2 gap-3">
        <x-sf::form.button variant="outline" data-sheet-close>{{ __('storefront::account.cancel') }}</x-sf::form.button>
        <x-sf::form.button variant="danger" data-confirm-delete :busy="__('storefront::account.deleting')">{{ __('identity::addresses.delete') }}</x-sf::form.button>
    </div>
</x-sf::modal>
