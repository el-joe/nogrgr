{{--
    Vendor › Your store › Mobile app (VEN-037 / 037a, Figma Vendor Panel (new screens)
    200:37622 aligned to the Tenant Panel): page title + Save, the shared settings form
    (api::panel._form). $page, $canManage.
--}}
<x-vendor::layout :title="__('api::panel.title')" page="vendor-mobile-app">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('api::panel.title')" :subtitle="__('api::panel.subtitle')" :breadcrumbs="[__('navigation.vendor.store_design') => null, __('api::panel.title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button type="submit" form="vendor-mobile-app-form" icon="check">{{ __('api::panel.save') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @include('api::panel._form', [
            'page' => $page,
            'formId' => 'vendor-mobile-app-form',
            'action' => vendor_route('store.mobile-app.update'),
            'canManage' => $canManage,
            'pushNote' => $page['pushReady'] ? __('api::panel.push.ready') : __('api::panel.push.missing'),
        ])
    </div>
</x-vendor::layout>
