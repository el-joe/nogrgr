{{--
    Vendor profile › Impersonate (ADM-041i, Figma 72:52459 left): reason + details and
    the audit warning. vendors.js posts it and opens the returned vendor panel URL.
--}}
<x-ui::modal id="impersonate" :title="__('platform::vendors.impersonate.title', ['name' => $tenant->name])" :subtitle="__('platform::vendors.impersonate.subtitle')">
    <form id="impersonate-form" method="POST" action="{{ route('admin.vendors.impersonate', $tenant->id) }}" data-vendor-impersonate data-blocked="{{ __('platform::vendors.impersonate.blocked') }}" class="grid gap-5" novalidate>
        @csrf
        <x-ui::select name="reason" id="impersonate-reason" :label="__('platform::vendors.impersonate.reason')" :options="$reasons" :placeholder="__('platform::vendors.impersonate.reason_placeholder')" :search="false" required />
        <x-ui::textarea name="details" id="impersonate-details" :label="__('platform::vendors.impersonate.details')" :helper="__('platform::vendors.impersonate.details_hint')" :maxlength="1000" :rows="4" />
        <div class="flex gap-3 rounded-card border border-warning/30 bg-warning-bg p-4 text-sm text-ink" role="note">
            <x-ui::icon name="alert-triangle" :size="18" class="mt-0.5 shrink-0 text-warning" />
            <p>{{ __('platform::vendors.impersonate.warning') }}</p>
        </div>
    </form>
    <x-slot:footer>
        <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
        <x-ui::button type="submit" form="impersonate-form" icon="eye">{{ __('platform::vendors.impersonate.start') }}</x-ui::button>
    </x-slot:footer>
</x-ui::modal>
