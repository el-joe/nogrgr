{{-- DNS check pill: all records OK / "CNAME missing" / not checked / error. --}}
@use('App\Modules\Platform\Enums\DnsCheckStatus')
@switch($request->dns_status)
    @case(DnsCheckStatus::Ok)
        <x-ui::badge tone="success">{{ __('platform::domains.dns_check.ok') }}</x-ui::badge>
        @break
    @case(DnsCheckStatus::Missing)
        <x-ui::badge tone="warning">{{ __('platform::domains.dns_check.missing', ['types' => implode(', ', $request->missingRecordTypes()) ?: __('platform::domains.dns_check.records')]) }}</x-ui::badge>
        @break
    @case(DnsCheckStatus::Error)
        <x-ui::badge tone="danger">{{ $request->dns_status->label() }}</x-ui::badge>
        @break
    @default
        <x-ui::badge tone="neutral">{{ __('platform::domains.dns_check.unchecked') }}</x-ui::badge>
@endswitch
