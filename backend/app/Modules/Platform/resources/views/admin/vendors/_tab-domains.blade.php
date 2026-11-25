{{--
    Vendor profile › Domains (ADM-041c, Figma 72:51480): the sub-domain (primary) and
    every custom-domain request with status, DNS and SSL. Actions post to the domains
    pages (admin.vendors.domains.*); each is shown only when its route exists and the
    admin holds vendors.domains.manage.
--}}
@use('App\Modules\Platform\Enums\DomainRequestStatus')
@use('Illuminate\Support\Facades\Route')
@php
    $canDomains = (bool) auth('admin')->user()?->can('vendors.domains.manage');
    $can = fn (string $name) => $canDomains && Route::has('admin.vendors.domains.'.$name);
@endphp
<x-ui::card :title="__('platform::vendors.domains.title')" :padding="false" id="vendor-domains">
    @if ($can('store'))
        <x-slot:actions>
            <x-ui::button size="sm" icon="plus" data-overlay-open="add-domain">{{ __('platform::vendors.domains.add') }}</x-ui::button>
        </x-slot:actions>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="bg-subtle text-xs font-medium text-ink-secondary">
                <tr>
                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.domains.columns.domain') }}</th>
                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.domains.columns.type') }}</th>
                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.domains.columns.status') }}</th>
                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.domains.columns.dns') }}</th>
                    <th scope="col" class="px-5 py-3 text-start font-medium">{{ __('platform::vendors.domains.columns.ssl') }}</th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('ui.table.row_actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-t border-line">
                    <td class="px-5 py-4 font-medium text-ink" dir="ltr">{{ $tenant->subdomainHost() }}</td>
                    <td class="px-5 py-4 text-ink">{{ __('platform::vendors.domains.subdomain') }}</td>
                    <td class="px-5 py-4"><x-ui::badge tone="success">{{ __('platform::vendors.domains.primary') }}</x-ui::badge></td>
                    <td class="px-5 py-4 text-ink-muted">—</td>
                    <td class="px-5 py-4 text-ink-muted">—</td>
                    <td class="px-5 py-4"></td>
                </tr>
                @foreach ($domainRequests as $request)
                    <tr class="border-t border-line">
                        <td class="px-5 py-4">
                            <span class="grid">
                                @if ($can('show'))
                                    <a href="{{ route('admin.vendors.domains.show', $request->id) }}" class="font-medium text-ink hover:underline" dir="ltr">{{ $request->host }}</a>
                                @else
                                    <span class="font-medium text-ink" dir="ltr">{{ $request->host }}</span>
                                @endif
                                @if ($request->ssl_expires_at)
                                    <span class="text-xs text-ink-secondary">{{ __('platform::vendors.domains.ssl_until', ['date' => $request->ssl_expires_at->translatedFormat('M Y')]) }}</span>
                                @endif
                            </span>
                        </td>
                        <td class="px-5 py-4 text-ink">{{ __('platform::vendors.domains.custom') }}</td>
                        <td class="px-5 py-4"><x-ui::badge :tone="$request->status->tone()">{{ $request->status->label() }}</x-ui::badge></td>
                        <td class="px-5 py-4"><x-ui::badge :tone="$request->dns_status->tone()">{{ $request->dns_status->label() }}</x-ui::badge></td>
                        <td class="px-5 py-4"><x-ui::badge :tone="$request->ssl_status->tone()">{{ $request->ssl_status->label() }}</x-ui::badge></td>
                        <td class="px-5 py-4">
                            <div class="flex items-center justify-end gap-1">
                                @if ($can('show'))
                                    <x-ui::icon-button icon="eye" size="sm" :href="route('admin.vendors.domains.show', $request->id)" :label="__('platform::vendors.domains.view', ['host' => $request->host])" />
                                @endif
                                @php
                                    $actions = array_filter([
                                        'check' => $request->status !== DomainRequestStatus::Removed && $can('check'),
                                        'connect' => $request->status->canTransitionTo(DomainRequestStatus::Connected) && $can('connect'),
                                        'reopen' => $request->status->canTransitionTo(DomainRequestStatus::Pending) && $can('reopen'),
                                        'remove' => $request->status->canTransitionTo(DomainRequestStatus::Removed) && $can('remove'),
                                    ]);
                                @endphp
                                @if ($actions !== [])
                                    <x-ui::row-actions :label="__('platform::vendors.actions.more', ['name' => $request->host])">
                                        @isset($actions['check'])
                                            <x-ui::dropdown-item icon="refresh" data-vendor-post :data-url="route('admin.vendors.domains.check', $request->id)">{{ __('platform::vendors.domains.check') }}</x-ui::dropdown-item>
                                        @endisset
                                        @isset($actions['connect'])
                                            <x-ui::dropdown-item
                                                icon="link"
                                                :data-confirm="__('platform::vendors.domains.confirm_connect')"
                                                :data-confirm-title="__('platform::vendors.domains.connect').' · '.$request->host"
                                                :data-confirm-label="__('platform::vendors.domains.connect')"
                                                data-confirm-tone="primary"
                                                data-confirm-method="POST"
                                                :data-confirm-url="route('admin.vendors.domains.connect', $request->id)"
                                                data-vendor-reload
                                            >{{ __('platform::vendors.domains.connect') }}</x-ui::dropdown-item>
                                        @endisset
                                        @isset($actions['reopen'])
                                            <x-ui::dropdown-item
                                                icon="return"
                                                :data-confirm="__('platform::vendors.domains.confirm_reopen')"
                                                :data-confirm-title="__('platform::vendors.domains.reopen').' · '.$request->host"
                                                :data-confirm-label="__('platform::vendors.domains.reopen')"
                                                data-confirm-tone="primary"
                                                data-confirm-method="POST"
                                                :data-confirm-url="route('admin.vendors.domains.reopen', $request->id)"
                                                data-vendor-reload
                                            >{{ __('platform::vendors.domains.reopen') }}</x-ui::dropdown-item>
                                        @endisset
                                        @isset($actions['remove'])
                                            <x-ui::dropdown-divider />
                                            <x-ui::dropdown-item
                                                icon="trash"
                                                tone="danger"
                                                :data-confirm="__('platform::vendors.domains.confirm_remove')"
                                                :data-confirm-title="__('platform::vendors.domains.remove').' · '.$request->host"
                                                :data-confirm-label="__('platform::vendors.domains.remove')"
                                                data-confirm-method="POST"
                                                :data-confirm-url="route('admin.vendors.domains.remove', $request->id)"
                                                data-vendor-reload
                                            >{{ __('platform::vendors.domains.remove') }}</x-ui::dropdown-item>
                                        @endisset
                                    </x-ui::row-actions>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($domainRequests->isEmpty())
        <p class="border-t border-line px-5 py-4 text-sm text-ink-secondary">{{ __('platform::vendors.domains.empty') }}</p>
    @endif
</x-ui::card>

@if ($can('store'))
    <x-ui::modal id="add-domain" :title="__('platform::vendors.domains.add')" :subtitle="__('platform::vendors.domains.add_hint')" size="sm">
        <form id="add-domain-form" method="POST" action="{{ route('admin.vendors.domains.store', $tenant->id) }}" data-ajax data-vendor-reload-form class="grid gap-5" novalidate>
            @csrf
            <x-ui::input name="host" :label="__('platform::vendors.domains.host')" :placeholder="__('platform::vendors.domains.host_placeholder')" required maxlength="253" autocomplete="off" spellcheck="false" dir="ltr" data-autofocus />
        </form>
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="add-domain-form" icon="plus">{{ __('platform::vendors.domains.add_submit') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
@endif
