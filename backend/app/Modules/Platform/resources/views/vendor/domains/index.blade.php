{{--
    Vendor › Settings › Domains (VEN-082 / 082a, Vendor Panel (new screens) 91:116087):
    domains table (sub-domain + custom domains with primary / connected / DNS pending),
    and beside it the selected domain's DNS records (type, host, value, check) with
    "Re-check DNS", "Verify & connect" once every record is found, and the SSL state
    ("SSL pending" until a certificate is seen — issued outside the app). Add domain
    modal shows the records to create before saving.
--}}
@use('App\Modules\Platform\Enums\DnsCheckStatus')
@use('App\Modules\Platform\Enums\DomainRequestStatus')
@use('App\Modules\Platform\Enums\SslStatus')
@php
    $rows = $selected?->dns_result ?? [];
    $checked = $selected?->last_checked_at;
@endphp
<x-vendor::layout :title="$selected ? $selected->host : __('platform::vendor_domains.title')" page="vendor-domains">
    <div class="grid gap-6">
        <x-ui::page-header :title="__('platform::vendor_domains.title')" :subtitle="__('platform::vendor_domains.subtitle')" :breadcrumbs="[__('navigation.admin.settings') => null, __('platform::vendor_domains.title') => $selected ? vendor_route('settings.domains.index') : null] + ($selected ? [$selected->host => null] : [])">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-overlay-open="vendor-domain-add">{{ __('platform::vendor_domains.add') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,520px)]">
            <x-ui::datatable
                id="vendor-domains"
                :url="vendor_route('settings.domains.data')"
                row-key="key"
                :columns="[
                    ['data' => 'host', 'title' => __('platform::vendor_domains.columns.domain'), 'orderable' => false],
                    ['data' => 'status', 'title' => __('platform::vendor_domains.columns.status'), 'orderable' => false, 'searchable' => false],
                    ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
                ]"
                :empty-title="__('platform::vendor_domains.empty_title')"
                :empty-description="__('platform::vendor_domains.empty_description')"
            />

            <x-ui::card :padding="false" data-domain-detail>
                @if ($selected === null)
                    <div class="p-5">
                        <x-ui::empty-state compact icon="globe" :title="__('platform::vendor_domains.none_selected_title')" :description="__('platform::vendor_domains.none_selected')" />
                    </div>
                @else
                    <header class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold text-ink"><bdi dir="ltr">{{ $selected->host }}</bdi></h2>
                            <p class="mt-0.5 text-xs text-ink-secondary">{{ $checked ? __('platform::vendor_domains.last_checked', ['time' => $checked->diffForHumans()]) : __('platform::vendor_domains.not_checked') }}</p>
                        </div>
                        @if ($canManage && $selected->status !== DomainRequestStatus::Removed)
                            <div class="flex flex-wrap gap-2">
                                <x-ui::button variant="secondary" size="sm" icon="refresh" data-domain-check :data-url="vendor_route('settings.domains.check', ['domain' => $selected->id])">{{ __('platform::vendor_domains.recheck') }}</x-ui::button>
                                @if ($selected->status === DomainRequestStatus::Pending && $selected->dns_status === DnsCheckStatus::Ok)
                                    <x-ui::button size="sm" icon="check" :href="vendor_route('settings.domains.connect', ['domain' => $selected->id])" data-confirm-method="POST" data-confirm-tone="brand" :data-confirm-title="__('platform::vendor_domains.connect')" :data-confirm="__('platform::vendor_domains.connect_confirm', ['host' => $selected->host])">{{ __('platform::vendor_domains.connect') }}</x-ui::button>
                                @endif
                            </div>
                        @endif
                    </header>

                    @if ($selected->status === DomainRequestStatus::Removed)
                        <p class="mx-5 mt-4 flex items-start gap-2 rounded-control bg-subtle px-4 py-3 text-sm text-ink-secondary" role="status"><x-ui::icon name="info" :size="18" class="shrink-0" />{{ __('platform::vendor_domains.removed_notice') }}</p>
                    @endif

                    <div class="m-5 overflow-x-auto rounded-control border border-line">
                        <table class="ui-table w-full min-w-[420px] text-sm">
                            <thead>
                                <tr class="border-b border-line bg-subtle text-xs font-medium text-ink-secondary">
                                    <th scope="col" class="px-4 py-3 text-start">{{ __('platform::vendor_domains.dns.type') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start">{{ __('platform::vendor_domains.dns.host') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start">{{ __('platform::vendor_domains.dns.value') }}</th>
                                    <th scope="col" class="px-4 py-3 text-start">{{ __('platform::vendor_domains.dns.check') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows !== [] ? $rows : $instructions as $record)
                                    @php
                                        $checkedRow = array_key_exists('ok', $record);
                                        $ok = (bool) ($record['ok'] ?? false);
                                    @endphp
                                    <tr class="border-b border-line last:border-b-0" data-dns-row="{{ $checkedRow ? ($ok ? 'found' : 'missing') : 'waiting' }}">
                                        <td class="px-4 py-3 font-medium text-ink">{{ $record['type'] }}</td>
                                        <td class="px-4 py-3"><bdi dir="ltr" class="font-mono text-[13px]">{{ $record['host'] }}</bdi></td>
                                        <td class="px-4 py-3"><bdi dir="ltr" class="break-all font-mono text-[13px]">{{ $record['expected'] ?? $record['value'] }}</bdi></td>
                                        <td class="px-4 py-3">
                                            @if (! $checkedRow)
                                                <x-ui::badge tone="neutral" dot>{{ __('platform::vendor_domains.dns.waiting') }}</x-ui::badge>
                                            @else
                                                <x-ui::badge :tone="$ok ? 'success' : 'danger'" dot>{{ $ok ? __('platform::vendor_domains.dns.found') : __('platform::vendor_domains.dns.missing') }}</x-ui::badge>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-ink-secondary">{{ __('platform::vendor_domains.dns.none') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <dl class="mx-5 grid gap-3 border-t border-line py-4 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-secondary">{{ __('platform::vendor_domains.columns.status') }}</dt>
                            <dd>@include('platform::vendor.domains._status-cell', ['row' => ['request' => $selected]])</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-secondary">{{ __('platform::vendor_domains.ssl.title') }}</dt>
                            <dd>
                                @if ($selected->ssl_status === SslStatus::Valid)
                                    <x-ui::badge tone="success" dot>{{ __('platform::vendor_domains.ssl.valid', ['date' => $selected->ssl_expires_at?->translatedFormat('j M Y') ?? '—']) }}</x-ui::badge>
                                @elseif ($selected->dns_status !== DnsCheckStatus::Ok)
                                    <x-ui::badge tone="neutral" dot>{{ __('platform::vendor_domains.ssl.waiting_dns') }}</x-ui::badge>
                                @else
                                    <x-ui::badge tone="warning" dot>{{ __('platform::vendor_domains.ssl.pending') }}</x-ui::badge>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <p class="mx-5 mb-5 flex items-start gap-2 rounded-control bg-info-bg px-4 py-3 text-[13px] text-info">
                        <x-ui::icon name="info" :size="16" class="mt-0.5 shrink-0" />
                        <span>{{ __('platform::vendor_domains.propagation_note') }}</span>
                    </p>
                @endif
            </x-ui::card>
        </div>
    </div>

    @if ($canManage)
        <x-ui::modal id="vendor-domain-add" :title="__('platform::vendor_domains.add_title')" :subtitle="__('platform::vendor_domains.add_subtitle')">
            <form id="vendor-domain-add-form" method="POST" action="{{ vendor_route('settings.domains.store') }}" data-ajax novalidate class="grid gap-4">
                @csrf
                <x-ui::input name="host" :label="__('platform::vendor_domains.fields.host')" placeholder="shop.example.com" dir="ltr" required autocomplete="off" />
                <div class="grid gap-2">
                    <p class="text-[13px] font-medium text-ink">{{ __('platform::vendor_domains.records_to_add') }}</p>
                    <ul class="grid gap-1.5 rounded-control border border-line bg-subtle p-3 font-mono text-xs text-ink" dir="ltr" role="list">
                        @forelse ($preview as $record)
                            <li class="break-all">{{ $record['type'] }} · {{ $record['host'] }} → {{ $record['value'] }}</li>
                        @empty
                            <li class="font-sans text-ink-secondary">{{ __('platform::vendor_domains.dns.none') }}</li>
                        @endforelse
                    </ul>
                    <p class="text-xs text-ink-secondary">{{ __('platform::vendor_domains.records_hint') }}</p>
                </div>
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="vendor-domain-add-form" icon="plus">{{ __('platform::vendor_domains.add') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
