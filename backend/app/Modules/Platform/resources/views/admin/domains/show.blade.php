{{--
    Admin › Vendors › Domains › request detail (ADM-045a): DNS instructions for the store
    (platform templates with {domain} / {subdomain} / {token} filled in), the last DNS /
    SSL check (expected vs found per record) and the request actions (check now, connect,
    remove, reopen — vendors.domains.manage).
--}}
@php
    $admin = auth('admin')->user();
    $canManage = (bool) $admin?->can('vendors.domains.manage');
    $canStore = (bool) $admin?->can('vendors.view');
    $locale = app()->getLocale();
    $date = fn (?\Illuminate\Support\Carbon $value) => $value?->locale($locale)->translatedFormat('j M Y, H:i');
    $tenant = $request->tenant;
    $rows = $request->dns_result ?? [];
@endphp
<x-layouts.panel surface="admin" :title="$request->host" data-page="admin-vendor-reviews">
    <div class="grid gap-6" data-reviews="domain" data-reviews-reload>
        <x-ui::page-header
            :title="$request->host"
            :subtitle="$tenant ? __('platform::domains.show_subtitle', ['store' => $tenant->name]) : null"
            :breadcrumbs="[__('navigation.sections.vendors') => route('admin.vendors.index'), __('platform::domains.title') => route('admin.vendors.domains.index'), $request->host => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui::badge>
            </x-slot:status>
            @if ($canManage)
                <x-slot:actions>
                    @include('platform::admin.domains._request-actions', ['request' => $request, 'asMenu' => false])
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_350px]">
            <div class="grid gap-6">
                <x-ui::card :title="__('platform::domains.instructions.title')" :subtitle="__('platform::domains.instructions.subtitle')" id="dns-instructions" :padding="false">
                    @if ($instructions === [])
                        <div class="px-5 pb-5">
                            <x-ui::empty-state compact icon="server" :title="__('platform::domains.instructions.empty_title')" :description="__('platform::domains.instructions.empty_description')">
                                <x-ui::button variant="secondary" :href="route('admin.vendors.domains.index', ['tab' => 'dns'])">{{ __('platform::domains.instructions.manage_templates') }}</x-ui::button>
                            </x-ui::empty-state>
                        </div>
                    @else
                        <div class="relative overflow-x-auto">
                            <table class="ui-table w-full text-sm">
                                <thead>
                                    <tr class="border-y border-line bg-subtle text-start text-xs font-medium text-ink-secondary">
                                        <th scope="col" class="px-5 py-3 text-start">{{ __('platform::domains.dns.fields.type') }}</th>
                                        <th scope="col" class="px-5 py-3 text-start">{{ __('platform::domains.dns.fields.host') }}</th>
                                        <th scope="col" class="px-5 py-3 text-start">{{ __('platform::domains.dns.fields.value') }}</th>
                                        <th scope="col" class="px-5 py-3 text-end">{{ __('platform::domains.dns.fields.ttl') }}</th>
                                        <th scope="col" class="px-5 py-3 text-end">{{ __('platform::domains.dns.fields.priority') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($instructions as $record)
                                        <tr class="border-b border-line last:border-b-0">
                                            <td class="px-5 py-3"><x-ui::badge :tone="\App\Modules\Platform\Enums\DnsRecordType::from($record['type'])->tone()">{{ $record['type'] }}</x-ui::badge></td>
                                            <td class="px-5 py-3"><bdi dir="ltr" class="font-mono text-[13px] text-ink">{{ $record['host'] }}</bdi></td>
                                            <td class="px-5 py-3"><bdi dir="ltr" class="break-all font-mono text-[13px] text-ink">{{ $record['value'] }}</bdi></td>
                                            <td class="px-5 py-3 text-end"><bdi dir="ltr">{{ $record['ttl'] }}</bdi></td>
                                            <td class="px-5 py-3 text-end">{{ $record['priority'] ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui::card>

                <x-ui::card :title="__('platform::domains.check.title')" :subtitle="$request->last_checked_at ? __('platform::domains.check.checked_at', ['date' => $date($request->last_checked_at)]) : null" id="dns-check" :padding="false">
                    @if ($rows === [])
                        <div class="px-5 pb-5">
                            <x-ui::empty-state compact icon="refresh" :title="__('platform::domains.check.empty_title')" :description="__('platform::domains.check.empty_description')" />
                        </div>
                    @else
                        <div class="relative overflow-x-auto">
                            <table class="ui-table w-full text-sm">
                                <thead>
                                    <tr class="border-y border-line bg-subtle text-xs font-medium text-ink-secondary">
                                        <th scope="col" class="px-5 py-3 text-start">{{ __('platform::domains.dns.fields.type') }}</th>
                                        <th scope="col" class="px-5 py-3 text-start">{{ __('platform::domains.dns.fields.host') }}</th>
                                        <th scope="col" class="px-5 py-3 text-start">{{ __('platform::domains.check.expected') }}</th>
                                        <th scope="col" class="px-5 py-3 text-start">{{ __('platform::domains.check.found') }}</th>
                                        <th scope="col" class="px-5 py-3 text-start">{{ __('platform::domains.check.result') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        <tr class="border-b border-line last:border-b-0">
                                            <td class="px-5 py-3 font-medium text-ink">{{ $row['type'] }}</td>
                                            <td class="px-5 py-3"><bdi dir="ltr" class="font-mono text-[13px]">{{ $row['host'] }}</bdi></td>
                                            <td class="px-5 py-3"><bdi dir="ltr" class="break-all font-mono text-[13px]">{{ $row['expected'] }}</bdi></td>
                                            <td class="px-5 py-3">
                                                @if (($row['found'] ?? []) === [])
                                                    <span class="text-ink-secondary">{{ __('platform::domains.check.nothing_found') }}</span>
                                                @else
                                                    <bdi dir="ltr" class="break-all font-mono text-[13px]">{{ implode(', ', $row['found']) }}</bdi>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3">
                                                <x-ui::badge :tone="($row['ok'] ?? false) ? 'success' : 'warning'">{{ ($row['ok'] ?? false) ? __('platform::domains.check.ok') : __('platform::domains.check.missing') }}</x-ui::badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-ui::card>
            </div>

            <x-ui::card :title="__('platform::domains.details.title')" id="domain-details">
                <dl class="grid gap-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-ink-secondary">{{ __('platform::domains.columns.store') }}</dt>
                        <dd class="min-w-0 text-end font-medium text-ink">
                            @if ($tenant && $canStore)
                                <a href="{{ route('admin.vendors.show', $tenant->id) }}" class="hover:underline">{{ $tenant->name }}</a>
                            @else
                                {{ $tenant?->name ?? '—' }}
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-ink-secondary">{{ __('platform::domains.details.store_address') }}</dt>
                        <dd class="min-w-0 break-all text-end font-medium text-ink"><bdi dir="ltr">{{ $tenant?->subdomainHost() ?? '—' }}</bdi></dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-secondary">{{ __('platform::domains.columns.dns') }}</dt>
                        <dd>@include('platform::admin.domains._dns-cell', ['request' => $request])</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-secondary">{{ __('platform::domains.columns.ssl') }}</dt>
                        <dd>@include('platform::admin.domains._ssl-cell', ['request' => $request])</dd>
                    </div>
                    @if ($request->ssl_expires_at)
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-ink-secondary">{{ __('platform::domains.details.ssl_expires') }}</dt>
                            <dd class="text-end font-medium text-ink">{{ $date($request->ssl_expires_at) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-ink-secondary">{{ __('platform::domains.details.requested') }}</dt>
                        <dd class="text-end font-medium text-ink">{{ $date($request->created_at) ?? '—' }}</dd>
                    </div>
                    @if ($request->connected_at)
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-ink-secondary">{{ __('platform::domains.details.connected') }}</dt>
                            <dd class="text-end font-medium text-ink">{{ $date($request->connected_at) }}</dd>
                        </div>
                    @endif
                    @if ($request->removed_at)
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-ink-secondary">{{ __('platform::domains.details.removed') }}</dt>
                            <dd class="text-end font-medium text-ink">{{ $date($request->removed_at) }}</dd>
                        </div>
                    @endif
                </dl>
                <p class="mt-4 text-xs text-ink-secondary">{{ __('platform::domains.details.check_hint') }}</p>
            </x-ui::card>
        </div>
    </div>
</x-layouts.panel>
