{{--
    Owner portal › Domain › DNS check (OWN-004c, Figma Affiliate & Owner 122:103882):
    status, alert with the number of wrong records, DNS records table (type, host,
    expected, found, pass / fail per record), the records to add when nothing was
    checked yet, SSL certificate card; Edit (pending only) and Check again (queued).
--}}
@use('App\Modules\Platform\Enums\DnsCheckStatus')
@use('App\Modules\Platform\Enums\DomainRequestStatus')
@php
    $rows = $request->dns_result ?? [];
    $failed = collect($rows)->reject(fn ($row) => $row['ok'] ?? false)->count();
    $checked = $request->last_checked_at;
@endphp
<x-layouts.panel surface="owner" :title="$request->host" data-page="owner-domains">
    <div class="grid gap-6" data-domain-detail>
        <x-ui::page-header
            :title="$request->host"
            :subtitle="__('platform::owner.domains.show_subtitle', ['store' => $request->tenant?->name])"
            :breadcrumbs="[__('platform::owner.domains.title') => route('owner.domains.index'), $request->host => null]"
        >
            <x-slot:status>
                @include('platform::admin.domains._dns-cell', ['request' => $request])
            </x-slot:status>
            @if ($request->status !== DomainRequestStatus::Removed)
                <x-slot:actions>
                    @if ($request->status === DomainRequestStatus::Pending)
                        <x-ui::button variant="secondary" icon="edit" data-remote-modal="domain-edit-modal" :data-url="route('owner.domains.edit', $request->id)">{{ __('platform::owner.domains.edit_button') }}</x-ui::button>
                    @endif
                    <x-ui::button variant="secondary" icon="trash" :href="route('owner.domains.destroy', ['domain' => $request->id, 'from_detail' => 1])" data-confirm-method="DELETE" :data-confirm="__('platform::owner.domains.remove_confirm', ['host' => $request->host])" :data-confirm-title="__('platform::owner.domains.remove_title')">{{ __('platform::owner.domains.remove') }}</x-ui::button>
                    <x-ui::button icon="refresh" data-domain-check :data-url="route('owner.domains.check', $request->id)">{{ __('platform::owner.domains.check_again') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @if ($request->status === DomainRequestStatus::Removed)
            <p class="flex items-start gap-2 rounded-card bg-subtle px-4 py-3 text-sm text-ink-secondary" role="status">
                <x-ui::icon name="info" :size="18" class="shrink-0" />{{ __('platform::owner.domains.removed_notice') }}
            </p>
        @elseif ($request->dns_status === DnsCheckStatus::Ok)
            <p class="flex items-start gap-2 rounded-card bg-success-bg px-4 py-3 text-sm text-ink" role="status">
                <x-ui::icon name="check-circle" :size="18" class="shrink-0 text-success" />{{ __('platform::owner.domains.all_ok') }}
            </p>
        @elseif ($failed > 0)
            <p class="flex items-start gap-2 rounded-card bg-danger-bg px-4 py-3 text-sm text-ink" role="alert">
                <x-ui::icon name="x-circle" :size="18" class="shrink-0 text-danger" />{{ trans_choice('platform::owner.domains.wrong_records', $failed, ['count' => $failed, 'total' => count($rows)]) }}
            </p>
        @endif

        <x-ui::card :title="__('platform::owner.domains.dns_title')" :subtitle="$checked ? __('platform::owner.domains.last_checked', ['time' => $checked->diffForHumans()]) : __('platform::owner.domains.not_checked')" :padding="false">
            @if ($rows === [] && $instructions === [])
                <div class="px-5 pb-5">
                    <x-ui::empty-state compact icon="server" :title="__('platform::owner.domains.no_records_title')" :description="__('platform::owner.domains.no_records')" />
                </div>
            @else
                <div class="relative overflow-x-auto">
                    <table class="ui-table w-full text-sm">
                        <thead>
                            <tr class="border-y border-line bg-subtle text-xs font-medium text-ink-secondary">
                                <th scope="col" class="px-5 py-3 text-start">{{ __('platform::owner.domains.dns.type') }}</th>
                                <th scope="col" class="px-5 py-3 text-start">{{ __('platform::owner.domains.dns.host') }}</th>
                                <th scope="col" class="px-5 py-3 text-start">{{ __('platform::owner.domains.dns.expected') }}</th>
                                <th scope="col" class="px-5 py-3 text-start">{{ __('platform::owner.domains.dns.found') }}</th>
                                <th scope="col" class="px-5 py-3 text-start">{{ __('platform::owner.domains.dns.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($rows !== [])
                                @foreach ($rows as $row)
                                    @php $ok = (bool) ($row['ok'] ?? false); @endphp
                                    <tr class="border-b border-line last:border-b-0" data-dns-row="{{ $ok ? 'pass' : 'fail' }}">
                                        <td class="px-5 py-3 font-medium text-ink">{{ $row['type'] }}</td>
                                        <td class="px-5 py-3"><bdi dir="ltr" class="font-mono text-[13px]">{{ $row['host'] }}</bdi></td>
                                        <td class="px-5 py-3"><bdi dir="ltr" class="break-all font-mono text-[13px]">{{ $row['expected'] }}</bdi></td>
                                        <td class="px-5 py-3">
                                            @if (($row['found'] ?? []) === [])
                                                <span class="text-danger">{{ __('platform::owner.domains.dns.nothing') }}</span>
                                            @else
                                                <bdi dir="ltr" @class(['break-all font-mono text-[13px]', 'text-danger' => ! $ok])>{{ implode(', ', $row['found']) }}</bdi>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3"><x-ui::badge :tone="$ok ? 'success' : 'danger'">{{ $ok ? __('platform::owner.domains.dns.pass') : __('platform::owner.domains.dns.fail') }}</x-ui::badge></td>
                                    </tr>
                                @endforeach
                            @else
                                @foreach ($instructions as $record)
                                    <tr class="border-b border-line last:border-b-0" data-dns-row="pending">
                                        <td class="px-5 py-3 font-medium text-ink">{{ $record['type'] }}</td>
                                        <td class="px-5 py-3"><bdi dir="ltr" class="font-mono text-[13px]">{{ $record['host'] }}</bdi></td>
                                        <td class="px-5 py-3"><bdi dir="ltr" class="break-all font-mono text-[13px]">{{ $record['value'] }}</bdi></td>
                                        <td class="px-5 py-3 text-ink-muted">—</td>
                                        <td class="px-5 py-3"><x-ui::badge tone="neutral">{{ __('platform::owner.domains.dns.waiting') }}</x-ui::badge></td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui::card>

        <x-ui::card :title="__('platform::owner.domains.ssl_title')">
            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::owner.domains.ssl.status') }}</dt>
                    <dd>
                        @if ($request->ssl_status === \App\Modules\Platform\Enums\SslStatus::Unknown && $request->dns_status !== DnsCheckStatus::Ok)
                            <x-ui::badge tone="warning">{{ __('platform::owner.domains.ssl.waiting_dns') }}</x-ui::badge>
                        @else
                            @include('platform::admin.domains._ssl-cell', ['request' => $request])
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::owner.domains.ssl.issuer') }}</dt>
                    <dd class="font-medium text-ink">Let’s Encrypt</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::owner.domains.ssl.expires') }}</dt>
                    <dd class="font-medium text-ink">{{ $request->ssl_expires_at?->translatedFormat('j M Y') ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::owner.domains.ssl.auto_renew') }}</dt>
                    <dd class="font-medium text-ink">{{ __('platform::owner.domains.ssl.on') }}</dd>
                </div>
            </dl>
        </x-ui::card>
    </div>

    <x-ui::modal id="domain-edit-modal" :title="__('platform::owner.domains.edit_title')" size="sm">
        <div data-remote-loading class="grid gap-4" aria-busy="true">
            <x-ui::skeleton shape="rect" class="h-10 w-full" />
            <x-ui::skeleton shape="rect" class="h-10 w-2/3" />
        </div>
        <div data-remote-error hidden>
            <x-ui::empty-state compact variant="error" :description="__('platform::owner.domains.load_error')" />
            <div class="mt-3 flex justify-center">
                <x-ui::button variant="secondary" icon="refresh" data-remote-retry>{{ __('ui.retry') }}</x-ui::button>
            </div>
        </div>
        <div data-remote-body hidden></div>
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="domain-edit-form" icon="check" data-remote-submit>{{ __('platform::owner.domains.save') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</x-layouts.panel>
