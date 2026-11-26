{{-- Row actions: view DNS records, re-check, make primary, remove (custom domains only). --}}
@use('App\Modules\Platform\Enums\DomainRequestStatus')
@php
    $request = $row['request'];
    $domain = $row['domain'];
@endphp
<div class="flex items-center justify-end gap-1">
    @if ($request)
        <x-ui::icon-button icon="eye" size="sm" :href="vendor_route('settings.domains.show', ['domain' => $request->id])" :label="__('platform::vendor_domains.view', ['host' => $row['host']])" />
    @endif
    @if ($canManage && ($request || ! $row['is_primary']))
        <x-ui::row-actions :label="__('platform::vendor_domains.more', ['host' => $row['host']])">
            @if ($domain && ! $row['is_primary'])
                <x-ui::dropdown-item icon="star" :href="vendor_route('settings.domains.primary', ['domain' => $domain->id])" data-confirm-method="POST" :data-confirm="__('platform::vendor_domains.primary_confirm', ['host' => $row['host']])" :data-confirm-title="__('platform::vendor_domains.make_primary')" data-confirm-tone="brand">{{ __('platform::vendor_domains.make_primary') }}</x-ui::dropdown-item>
            @endif
            @if ($request)
                <x-ui::dropdown-item icon="refresh" data-domain-check :data-url="vendor_route('settings.domains.check', ['domain' => $request->id])">{{ __('platform::vendor_domains.recheck') }}</x-ui::dropdown-item>
                <x-ui::dropdown-item icon="trash" tone="danger" :href="vendor_route('settings.domains.destroy', ['domain' => $request->id])" data-confirm-method="DELETE" :data-confirm="__('platform::vendor_domains.remove_confirm', ['host' => $row['host']])" :data-confirm-title="__('platform::vendor_domains.remove_title')">{{ __('platform::vendor_domains.remove') }}</x-ui::dropdown-item>
            @endif
        </x-ui::row-actions>
    @endif
</div>
