{{--
    Admin › Vendors › Domains (ADM-045 Figma 73:55481, ADM-045b 73:56421). Tabs (?tab=):
    Domain requests (count, status filter, search domain / store) and DNS record
    templates (CRUD modal + "Shown to vendors as" preview). "Add record" sits in the
    header on the DNS tab only (vendors.domains.manage).
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('vendors.domains.manage');
    $breadcrumbs = $tab === 'dns'
        ? [__('navigation.sections.vendors') => route('admin.vendors.index'), __('platform::domains.title') => route('admin.vendors.domains.index'), __('platform::domains.dns.breadcrumb') => null]
        : [__('navigation.sections.vendors') => route('admin.vendors.index'), __('platform::domains.title') => null];
@endphp
<x-layouts.panel surface="admin" :title="__('platform::domains.title')" data-page="admin-vendor-reviews">
    <div class="grid gap-6" data-reviews="{{ $tab === 'dns' ? 'dns' : 'domains' }}" @if ($tab === 'dns') data-reviews-reload @endif>
        <x-ui::page-header :title="__('platform::domains.title')" :breadcrumbs="$breadcrumbs">
            @if ($tab === 'dns' && $canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-dns-new>{{ __('platform::domains.dns.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
            <x-slot:tabs>
                <x-ui::tabs id="domains-tabs" :active="$tab" :label="__('platform::domains.title')" :items="[
                    'requests' => ['label' => __('platform::domains.tabs.requests'), 'count' => $counts['all'] ?? 0, 'href' => route('admin.vendors.domains.index')],
                    'dns' => ['label' => __('platform::domains.tabs.dns'), 'count' => $templateCount, 'href' => route('admin.vendors.domains.index', ['tab' => 'dns'])],
                ]" />
            </x-slot:tabs>
        </x-ui::page-header>

        @if ($tab === 'dns')
            @include('platform::admin.domains._tab-dns')
        @else
            @include('platform::admin.domains._tab-requests')
        @endif
    </div>
</x-layouts.panel>
