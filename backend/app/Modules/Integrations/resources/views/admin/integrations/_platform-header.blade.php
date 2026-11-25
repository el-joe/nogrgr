{{--
    Admin › Settings › Platform header + tab bar (decision #69 business settings):
    Billing, Checkout & store API, Domains & DNS defaults.
    @param string $active  IntegrationSection value of the open tab
--}}
@php
    $tabs = [];

    foreach (\App\Modules\Integrations\Enums\IntegrationSection::business() as $tabSection) {
        $tabs[$tabSection->value] = ['label' => $tabSection->label(), 'href' => route('admin.settings.platform.show', $tabSection)];
    }
@endphp
<x-ui::page-header
    :title="__('integrations::admin.platform.title')"
    :subtitle="__('integrations::admin.platform.subtitle')"
    :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.settings') => null, __('integrations::admin.platform.title') => null]"
>
    <x-slot:tabs>
        <x-ui::tabs id="platform-settings-tabs" :active="$active" :label="__('integrations::admin.platform.title')" :items="$tabs" />
    </x-slot:tabs>
</x-ui::page-header>
