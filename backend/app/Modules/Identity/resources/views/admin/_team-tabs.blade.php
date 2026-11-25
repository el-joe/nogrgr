{{-- Admins | Roles tabs of System › Admins & roles (Figma Admin Panel 80:90095 / 80:91028). --}}
@php
    $actor = auth('admin')->user();
    $items = [];

    if ($actor?->can('admins.view')) {
        $items['admins'] = ['label' => __('identity::admins.title'), 'count' => $adminsCount, 'href' => route('admin.system.admins.index')];
    }

    if ($actor?->can('roles.view')) {
        $items['roles'] = ['label' => __('navigation.vendor.roles'), 'count' => $rolesCount, 'href' => route('admin.system.roles.index')];
    }
@endphp
<x-ui::tabs id="team-tabs" :active="$active" :label="__('identity::admins.breadcrumb')" :items="$items" />
