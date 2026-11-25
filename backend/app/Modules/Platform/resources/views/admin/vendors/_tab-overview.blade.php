{{--
    Vendor profile › Overview (ADM-041): Store info, Owner and Health cards. The KPI row
    and Sales chart need orders (P9) and are not shown.
--}}
@php
    $initials = collect(preg_split('/\s+/u', trim((string) $tenant->owner_name)) ?: [])->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $languageNames = collect($languages)->map(fn ($language) => $language['paid'] ? __('platform::vendors.profile.paid_language', ['name' => $language['name']]) : $language['name'])->implode(', ');
    $sellingNames = $countryNames['selling'] ?? [];
@endphp
<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_350px]">
    <x-ui::card :title="__('platform::vendors.profile.store_info')" id="store-info">
        <dl class="grid gap-3 text-sm">
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ __('platform::vendors.fields.store_name') }}</dt>
                <dd class="text-end font-medium text-ink">{{ $tenant->name }}</dd>
            </div>
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ __('platform::vendors.columns.host') }}</dt>
                <dd class="text-end font-medium text-ink" dir="ltr">{{ $tenant->subdomainHost() }}</dd>
            </div>
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ __('platform::vendors.fields.default_country') }}</dt>
                <dd class="text-end font-medium text-ink">{{ $countryNames['default'] ?? __('platform::vendors.profile.no_countries') }}</dd>
            </div>
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ __('platform::vendors.fields.countries') }}</dt>
                <dd class="text-end font-medium text-ink">{{ $sellingNames !== [] ? implode(', ', $sellingNames) : __('platform::vendors.profile.no_countries') }}</dd>
            </div>
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ __('platform::vendors.fields.languages') }}</dt>
                <dd class="text-end font-medium text-ink">{{ $languageNames }}</dd>
            </div>
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ __('platform::vendors.profile.custom_domain') }}</dt>
                <dd class="text-end font-medium text-ink">
                    @if ($customDomain)
                        <bdi dir="ltr">{{ $customDomain->host }}</bdi> ({{ $customDomain->status->label() }})
                    @else
                        {{ __('platform::vendors.profile.no_custom_domain') }}
                    @endif
                </dd>
            </div>
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ __('platform::vendors.fields.profit_percent') }}</dt>
                <dd class="text-end font-medium text-ink"><bdi dir="ltr">{{ \App\Modules\Platform\Services\VendorProfileService::percent($tenant->profit_percent_bps) }}</bdi></dd>
            </div>
            <div class="flex items-start justify-between gap-4">
                <dt class="text-ink-secondary">{{ __('platform::vendors.profile.created') }}</dt>
                <dd class="text-end font-medium text-ink">{{ $tenant->created_at?->translatedFormat('j M Y') ?? '—' }}</dd>
            </div>
        </dl>
    </x-ui::card>

    <div class="grid gap-6">
        <x-ui::card :title="__('platform::vendors.profile.owner')" id="owner-card">
            <div class="flex items-center gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-ink text-sm font-semibold text-surface" aria-hidden="true">{{ $initials !== '' ? $initials : '—' }}</span>
                <div class="grid min-w-0">
                    <span class="truncate font-medium text-ink">{{ $tenant->owner_name ?? '—' }}</span>
                    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $tenant->owner_email ?? '' }}</span>
                    <span class="truncate text-xs text-ink-secondary" dir="ltr">{{ $tenant->owner_phone ?? __('platform::vendors.profile.no_phone') }}</span>
                </div>
            </div>
        </x-ui::card>

        <x-ui::card :title="__('platform::vendors.profile.health')" id="health-card">
            <dl class="grid gap-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::vendors.profile.health_compliance') }}</dt>
                    <dd>
                        @if ($compliance)
                            <x-ui::badge :tone="$compliance->status->tone()">{{ $compliance->status->label() }}</x-ui::badge>
                        @else
                            <x-ui::badge tone="neutral">{{ __('platform::vendors.profile.not_submitted') }}</x-ui::badge>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::vendors.profile.health_domain') }}</dt>
                    <dd>
                        @if ($customDomain)
                            <x-ui::badge :tone="$customDomain->status->tone()">{{ $customDomain->status->label() }}</x-ui::badge>
                        @else
                            <x-ui::badge tone="success">{{ __('platform::vendors.profile.subdomain_only') }}</x-ui::badge>
                        @endif
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-ink-secondary">{{ __('platform::vendors.profile.health_provisioning') }}</dt>
                    <dd><x-ui::badge :tone="$tenant->status->tone()">{{ $tenant->status->label() }}</x-ui::badge></dd>
                </div>
            </dl>
        </x-ui::card>
    </div>
</div>
