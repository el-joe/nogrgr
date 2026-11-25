{{--
    Admin › Integrations header + tab bar (Figma Admin Panel 51:17971). Tabs of later
    phases (gateway limits, OpenAI tools, FCM, tracking defaults) are added when built.
    @param string $active  tab key (gateways | IntegrationSection value)
--}}
@php
    $tabs = ['gateways' => ['label' => __('integrations::admin.tabs.gateways'), 'href' => route('admin.integrations.gateways.index')]];

    foreach (\App\Modules\Integrations\Enums\IntegrationSection::integrations() as $tabSection) {
        $tabs[$tabSection->value] = ['label' => $tabSection->label(), 'href' => route('admin.integrations.show', $tabSection)];
    }
@endphp
<x-ui::page-header
    :title="__('integrations::admin.title')"
    :subtitle="__('integrations::admin.subtitle')"
    :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.settings') => null, __('integrations::admin.title') => null]"
>
    <x-slot:tabs>
        <x-ui::tabs id="integrations-tabs" :active="$active" :label="__('integrations::admin.title')" :items="$tabs" />
    </x-slot:tabs>
</x-ui::page-header>
