{{--
    Owner portal › Subscriptions (OWN-003, Figma Affiliate & Owner 122:102615): past-due
    alert, every store's subscription (server-side table) and Renew → gateway modal →
    hosted checkout. Inputs: $stores, $statuses, $pastDue, $gateways.
--}}
<x-layouts.panel surface="owner" :title="__('billing::owner.subscriptions.title')" data-page="owner-subscriptions">
    <x-ui::page-header :title="__('billing::owner.subscriptions.title')" :subtitle="__('billing::owner.subscriptions.subtitle')" />

    @if ($pastDue)
        @php
            $invoice = $pastDue->latestInvoice;
            $storeName = (string) $pastDue->tenant?->getAttribute('name');
        @endphp
        <div class="flex flex-wrap items-center gap-3 rounded-card bg-danger-bg px-4 py-3 text-sm text-ink" role="alert" data-past-due>
            <x-ui::icon name="x-circle" :size="18" class="shrink-0 text-danger" />
            <p class="min-w-0 flex-1">
                @if ($invoice)
                    {{ __('billing::owner.subscriptions.past_due_invoice', ['store' => $storeName, 'number' => $invoice->number, 'amount' => $invoice->total()->format()]) }}
                @else
                    {{ __('billing::owner.subscriptions.past_due', ['store' => $storeName]) }}
                @endif
            </p>
            @if ($pastDue->amount_minor > 0 && $gateways->isNotEmpty())
                <x-ui::button size="sm"
                    data-renew-open
                    :data-renew-url="route('owner.subscriptions.renew', $pastDue->id)"
                    :data-renew-store="$storeName"
                    :data-renew-plan="(string) $pastDue->plan?->name.' · '.$pastDue->term->label()"
                    :data-renew-amount="$pastDue->amount()->format()"
                >{{ __('billing::owner.subscriptions.pay_now') }}</x-ui::button>
            @endif
        </div>
    @endif

    <x-ui::datatable
        id="owner-subscriptions"
        :url="route('owner.subscriptions.data')"
        :columns="[
            ['data' => 'store', 'title' => __('billing::owner.columns.store'), 'orderable' => false, 'searchable' => false],
            ['data' => 'plan', 'title' => __('billing::owner.columns.plan'), 'orderable' => false, 'searchable' => false],
            ['data' => 'term', 'title' => __('billing::owner.columns.billing'), 'searchable' => false],
            ['data' => 'renews_on', 'name' => 'current_period_ends_at', 'title' => __('billing::owner.columns.renews_on'), 'searchable' => false],
            ['data' => 'amount_minor', 'title' => __('billing::owner.columns.amount'), 'align' => 'end', 'searchable' => false],
            ['data' => 'status', 'title' => __('billing::owner.columns.status'), 'searchable' => false],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[3, 'asc']]"
        :empty-title="__('billing::owner.subscriptions.empty_title')"
        :empty-description="__('billing::owner.subscriptions.empty_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="owner-subscriptions" :search-placeholder="__('billing::owner.search_store')">
                <x-ui::select name="tenant_id" :label="__('billing::owner.columns.store')" label-hidden :options="$stores" :placeholder="__('billing::owner.all_stores')" allow-clear data-filter :data-filter-label="__('billing::owner.columns.store')" class="w-44" />
                <x-ui::select name="status" :label="__('billing::owner.columns.status')" label-hidden :options="$statuses" :placeholder="__('billing::owner.columns.status')" :search="false" allow-clear data-filter :data-filter-label="__('billing::owner.columns.status')" class="w-40" />
            </x-ui::filters-bar>
        </x-slot:filters>
        <x-slot:empty>
            <x-ui::button icon="plus" :href="route('owner.stores.create')">{{ __('billing::owner.subscriptions.add_store') }}</x-ui::button>
        </x-slot:empty>
    </x-ui::datatable>

    <x-ui::modal id="renew-modal" :title="__('billing::owner.subscriptions.renew_title')" :subtitle="__('billing::owner.subscriptions.renew_subtitle')" size="sm">
        @if ($gateways->isEmpty())
            <x-ui::empty-state compact variant="error" icon="credit-card" :title="__('billing::owner.subscriptions.no_gateways_title')" :description="__('billing::owner.subscriptions.no_gateways')" />
        @else
            <form id="renew-form" method="POST" action="" class="grid gap-5" data-renew-form novalidate>
                @csrf
                <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 rounded-control bg-subtle px-4 py-3 text-sm">
                    <dt class="text-ink-secondary">{{ __('billing::owner.columns.store') }}</dt>
                    <dd class="text-end font-medium text-ink" data-renew-store-label></dd>
                    <dt class="text-ink-secondary">{{ __('billing::owner.columns.plan') }}</dt>
                    <dd class="text-end text-ink" data-renew-plan-label></dd>
                    <dt class="text-ink-secondary">{{ __('billing::owner.columns.amount') }}</dt>
                    <dd class="text-end font-semibold text-ink"><bdi dir="ltr" data-renew-amount-label></bdi></dd>
                </dl>
                <fieldset class="grid gap-3">
                    <legend class="mb-2 text-sm font-medium text-ink">{{ __('billing::owner.subscriptions.gateway') }}</legend>
                    @foreach ($gateways as $gateway)
                        <x-ui::radio name="gateway" :value="$gateway->code->value" :label="$gateway->code->brand()" :checked="$loop->first" />
                    @endforeach
                    @error('gateway')
                        <p class="text-xs text-danger">{{ $message }}</p>
                    @enderror
                </fieldset>
                <p class="flex items-start gap-2 text-xs text-ink-secondary">
                    <x-ui::icon name="shield" :size="16" class="shrink-0" />{{ __('billing::owner.subscriptions.secure_checkout') }}
                </p>
            </form>
        @endif
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            @if ($gateways->isNotEmpty())
                <x-ui::button type="submit" form="renew-form" icon="credit-card">{{ __('billing::owner.subscriptions.continue_payment') }}</x-ui::button>
            @endif
        </x-slot:footer>
    </x-ui::modal>
</x-layouts.panel>
