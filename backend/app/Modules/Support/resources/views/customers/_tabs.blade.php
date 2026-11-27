{{-- Vendor › Support tabs: platform tickets (VEN-091) and customer tickets. $active = platform | customers. --}}
<x-ui::tabs id="vendor-support-tabs" :active="$active" :label="__('support::tickets.vendor_title')" :items="[
    'platform' => ['label' => __('support::customer_tickets.tabs.platform'), 'href' => vendor_route('support.index')],
    'customers' => ['label' => __('support::customer_tickets.tabs.customers'), 'href' => vendor_route('support.customers.index')],
]" />
