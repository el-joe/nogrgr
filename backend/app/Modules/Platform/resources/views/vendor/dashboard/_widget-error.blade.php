{{-- Error state of one dashboard widget (its data failed to load; the rest of the dashboard still works). --}}
<div class="rounded-card border border-line bg-surface p-5 shadow-card">
    <x-ui::empty-state variant="error" compact :title="__('platform::dashboard_vendor.widgets.error_title')" :description="__('platform::dashboard_vendor.widgets.error_body')">
        <x-ui::button variant="secondary" icon="refresh" :href="vendor_route('dashboard')">{{ __('platform::dashboard_vendor.widgets.retry') }}</x-ui::button>
    </x-ui::empty-state>
</div>
