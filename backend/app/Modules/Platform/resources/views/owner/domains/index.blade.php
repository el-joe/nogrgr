{{--
    Owner portal › Domains (OWN-004, Figma Affiliate & Owner 122:103025): custom domains
    of all owned stores (server-side table: domain + store, SSL, DNS, status), the Add
    domain modal (OWN-004a, 122:103429 · validation 122:103792 · sheet on mobile
    122:105483) with the DNS records to add, and the Edit modal (OWN-004b, 122:103838).
    Inputs: $stores (id => name), $statuses.
--}}
<x-layouts.panel surface="owner" :title="__('platform::owner.domains.title')" data-page="owner-domains">
    <x-ui::page-header :title="__('platform::owner.domains.title')" :subtitle="__('platform::owner.domains.subtitle')">
        @if ($stores !== [])
            <x-slot:actions>
                <x-ui::button icon="plus" data-overlay-open="domain-add-modal">{{ __('platform::owner.domains.add') }}</x-ui::button>
            </x-slot:actions>
        @endif
    </x-ui::page-header>

    <x-ui::datatable
        id="owner-domains"
        :url="route('owner.domains.data')"
        :columns="[
            ['data' => 'host', 'title' => __('platform::owner.domains.columns.domain'), 'searchable' => false],
            ['data' => 'ssl_status', 'title' => __('platform::owner.domains.columns.ssl'), 'searchable' => false],
            ['data' => 'dns_status', 'title' => __('platform::owner.domains.columns.dns'), 'searchable' => false],
            ['data' => 'status', 'title' => __('platform::owner.domains.columns.status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[0, 'asc']]"
        :empty-title="__('platform::owner.domains.empty_title')"
        :empty-description="__('platform::owner.domains.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="owner-domains" :search-placeholder="__('platform::owner.domains.search')">
                <x-ui::select name="tenant_id" :label="__('platform::owner.domains.store')" label-hidden :options="$stores" :placeholder="__('platform::owner.domains.all_stores')" allow-clear data-filter :data-filter-label="__('platform::owner.domains.store')" class="w-44" />
                <x-ui::select name="status" :label="__('platform::owner.domains.columns.status')" label-hidden :options="$statuses" :placeholder="__('platform::owner.domains.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('platform::owner.domains.columns.status')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
        @if ($stores !== [])
            <x-slot:empty>
                <x-ui::button icon="plus" data-overlay-open="domain-add-modal">{{ __('platform::owner.domains.add') }}</x-ui::button>
            </x-slot:empty>
        @endif
    </x-ui::datatable>

    @if ($stores !== [])
        <x-ui::modal id="domain-add-modal" :title="__('platform::owner.domains.add_title')" :subtitle="__('platform::owner.domains.add_subtitle')" size="md">
            <form id="domain-add-form" method="POST" action="{{ route('owner.domains.store') }}" data-ajax data-domain-add class="grid gap-5"
                data-instructions-url="{{ route('owner.domains.instructions', ['tenant' => '__TENANT__']) }}" novalidate>
                @csrf
                <x-ui::select name="tenant_id" id="domain-add-store" :label="__('platform::owner.domains.store')" :options="$stores" :selected="array_key_first($stores)" required data-domain-store />
                <x-ui::input name="host" :label="__('platform::owner.domains.domain')" required maxlength="253" dir="ltr" autocomplete="off" spellcheck="false" placeholder="shop.example.com" :helper="__('platform::owner.domains.host_hint')" data-domain-host data-autofocus />
                <div class="grid gap-2">
                    <p class="text-sm font-medium text-ink">{{ __('platform::owner.domains.records_intro') }}</p>
                    <div class="min-h-16 rounded-control bg-sidebar px-4 py-3 font-mono text-[13px] leading-6 text-white" dir="ltr" data-domain-records aria-live="polite">
                        <span class="text-white/70" data-records-loading>{{ __('platform::owner.domains.records_loading') }}</span>
                    </div>
                    <p class="text-xs text-danger" data-records-error hidden>{{ __('platform::owner.domains.records_error') }}</p>
                    <p class="text-xs text-ink-secondary" data-records-empty hidden>{{ __('platform::owner.domains.records_empty') }}</p>
                </div>
            </form>
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="domain-add-form" icon="plus">{{ __('platform::owner.domains.add') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif

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
