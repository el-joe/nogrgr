{{--
    Domains › DNS record templates tab (ADM-045b): table (type, host, value, TTL,
    priority, actions), "Shown to vendors as" (records rendered for an example store)
    and the placeholders note. Create / edit through the record modal.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('vendors.domains.manage');
@endphp
<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_370px]">
    <x-ui::datatable
        id="dns-records"
        :url="route('admin.vendors.domains.dns.data')"
        :columns="[
            ['data' => 'type', 'title' => __('platform::domains.dns.fields.type'), 'orderable' => false, 'searchable' => false],
            ['data' => 'host', 'title' => __('platform::domains.dns.fields.host'), 'orderable' => false, 'searchable' => false],
            ['data' => 'value', 'title' => __('platform::domains.dns.fields.value'), 'orderable' => false, 'searchable' => false],
            ['data' => 'ttl', 'title' => __('platform::domains.dns.fields.ttl'), 'align' => 'end', 'orderable' => false, 'searchable' => false],
            ['data' => 'priority', 'title' => __('platform::domains.dns.fields.priority'), 'align' => 'end', 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :page-length="25"
        :empty-title="__('platform::domains.dns.empty_title')"
        :empty-description="__('platform::domains.dns.empty_description')"
    >
        @if ($canManage)
            <x-slot:empty>
                <x-ui::button icon="plus" data-dns-new>{{ __('platform::domains.dns.add') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>

    <x-ui::card :title="__('platform::domains.dns.preview_title')" id="dns-preview">
        @if ($preview === [])
            <p class="text-sm text-ink-secondary">{{ __('platform::domains.dns.preview_empty') }}</p>
        @else
            <div class="relative overflow-x-auto rounded-control bg-sidebar px-4 py-3 text-sidebar-ink" dir="ltr">
                <table class="w-full font-mono text-[13px] leading-6">
                    <caption class="sr-only">{{ __('platform::domains.dns.preview_caption') }}</caption>
                    <tbody>
                        @foreach ($preview as $record)
                            <tr>
                                <td class="whitespace-nowrap pe-3 align-top">{{ $record['host'] }}</td>
                                <td class="whitespace-nowrap pe-3 align-top">{{ $record['type'] }}@if ($record['priority'] !== null) <span class="text-sidebar-muted">{{ $record['priority'] }}</span>@endif</td>
                                <td class="break-all align-top">{{ $record['value'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-ink-secondary">{{ __('platform::domains.dns.preview_example') }}</p>
        @endif
        <div class="mt-4 flex gap-3 rounded-control bg-info-bg px-4 py-3 text-[13px] text-ink">
            <x-ui::icon name="info" :size="18" class="mt-0.5 shrink-0 text-info" />
            <ul class="grid gap-1">
                <li>{!! __('platform::domains.dns.placeholder_token', ['code' => '<code dir="ltr" class="font-mono">{token}</code>']) !!}</li>
                <li>{!! __('platform::domains.dns.placeholder_domain', ['code' => '<code dir="ltr" class="font-mono">{domain}</code>']) !!}</li>
                <li>{!! __('platform::domains.dns.placeholder_subdomain', ['code' => '<code dir="ltr" class="font-mono">{subdomain}</code>']) !!}</li>
            </ul>
        </div>
    </x-ui::card>
</div>

@if ($canManage)
    @include('platform::admin.domains._dns-modal')
@endif
