{{-- Vendor profile › Edit store (modal): store name, owner contact, countries. --}}
<x-ui::modal id="edit-store" :title="__('platform::vendors.profile.edit_store')" :subtitle="__('platform::vendors.profile.edit_store_hint')" size="lg">
    <form id="edit-store-form" method="POST" action="{{ route('admin.vendors.update', $tenant->id) }}" data-ajax class="grid gap-5" novalidate>
        @csrf
        @method('PUT')
        <x-ui::input name="name" id="edit-store-name" :label="__('platform::vendors.fields.store_name')" :value="$tenant->name" required maxlength="120" data-autofocus />
        <div class="grid gap-5 sm:grid-cols-2">
            <x-ui::input name="owner_name" id="edit-owner-name" :label="__('platform::vendors.fields.owner_name')" :value="$tenant->owner_name" required maxlength="120" />
            <x-ui::input name="owner_email" id="edit-owner-email" type="email" :label="__('platform::vendors.fields.owner_email')" :value="$tenant->owner_email" required maxlength="191" dir="ltr" />
            <x-ui::phone name="owner_phone" id="edit-owner-phone" :label="__('platform::vendors.fields.owner_phone')" :value="$tenant->owner_phone" />
            <x-ui::select name="default_country_id" id="edit-default-country" :label="__('platform::vendors.fields.default_country')" :options="$countries" :selected="$tenant->default_country_id" :placeholder="__('platform::vendors.fields.select_country')" allow-clear />
        </div>
        <x-ui::select name="selling_country_ids" id="edit-selling-countries" multiple :label="__('platform::vendors.fields.countries')" :helper="__('platform::vendors.fields.countries_hint')" :options="$countries" :selected="$tenant->selling_country_ids ?? []" :placeholder="__('platform::vendors.fields.select_countries')" />
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
        <x-ui::button type="submit" form="edit-store-form" icon="check">{{ __('platform::vendors.profile.save') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
