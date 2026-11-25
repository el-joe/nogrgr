{{--
    Requests center header (ADM-018, Figma 68:28760): breadcrumb, title, subtitle and the
    tab strip with an attention count per tab (unread / never opened / pending review).
    $tabs from RequestsCenterService::tabs(), $active = tab key.
--}}
@props(['tabs' => [], 'active' => 'products'])
<x-ui::page-header
    :title="__('catalog::requests.title')"
    :subtitle="__('catalog::requests.subtitle')"
    :breadcrumbs="[__('catalog::requests.breadcrumb') => null, __('catalog::requests.title') => null]"
>
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
    <x-slot:tabs>
        <x-ui::tabs id="requests-tabs" :active="$active" :label="__('catalog::requests.title')" :items="$tabs" />
    </x-slot:tabs>
</x-ui::page-header>
