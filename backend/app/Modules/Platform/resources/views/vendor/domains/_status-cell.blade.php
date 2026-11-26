{{-- Status pill: sub-domain Active, custom Connected (+ SSL pending) / DNS verified / DNS pending. --}}
@use('App\Modules\Platform\Enums\DnsCheckStatus')
@use('App\Modules\Platform\Enums\DomainRequestStatus')
@use('App\Modules\Platform\Enums\SslStatus')
@php $request = $row['request']; @endphp
<span class="flex flex-wrap items-center gap-1.5">
    @if ($request === null)
        <x-ui::badge tone="success" dot>{{ __('platform::vendor_domains.status.active') }}</x-ui::badge>
    @elseif ($request->status === DomainRequestStatus::Connected)
        <x-ui::badge tone="success" dot>{{ __('platform::vendor_domains.status.connected') }}</x-ui::badge>
        @if ($request->ssl_status !== SslStatus::Valid)
            <x-ui::badge tone="warning">{{ __('platform::vendor_domains.status.ssl_pending') }}</x-ui::badge>
        @endif
    @elseif ($request->dns_status === DnsCheckStatus::Ok)
        <x-ui::badge tone="info" dot>{{ __('platform::vendor_domains.status.verified') }}</x-ui::badge>
    @else
        <x-ui::badge tone="warning" dot>{{ __('platform::vendor_domains.status.dns_pending') }}</x-ui::badge>
    @endif
</span>
