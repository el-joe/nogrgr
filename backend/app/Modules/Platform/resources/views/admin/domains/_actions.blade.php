{{-- Domain requests › row actions: view, and the request actions in the more menu. --}}
@php
    $canManage = (bool) auth('admin')->user()?->can('vendors.domains.manage');
@endphp
<div class="flex items-center justify-end gap-1">
    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.vendors.domains.show', $request->id)" :label="__('platform::domains.actions.view', ['host' => $request->host])" />
    @if ($canManage)
        <x-ui::row-actions :label="__('platform::domains.actions.more', ['host' => $request->host])">
            @include('platform::admin.domains._request-actions', ['request' => $request, 'asMenu' => true])
        </x-ui::row-actions>
    @endif
</div>
