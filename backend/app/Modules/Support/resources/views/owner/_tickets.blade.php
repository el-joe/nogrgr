{{--
    Owner portal › Help & support › Support tickets (OWN-009, P9): the owner's tickets and
    their stores' (server-side list), New ticket (store picker) in a modal.
--}}
<section class="grid gap-4" data-owner-tickets aria-labelledby="owner-tickets-title">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="grid gap-0.5">
            <h2 id="owner-tickets-title" class="text-lg font-semibold text-ink">{{ __('support::tickets.owner_title') }}</h2>
            <p class="text-sm text-ink-secondary">{{ __('support::tickets.owner_subtitle') }}</p>
        </div>
        <x-ui::button icon="plus" data-remote-modal="owner-ticket-modal" :data-url="route('owner.support.tickets.create')">{{ __('support::tickets.new_ticket') }}</x-ui::button>
    </div>

    <x-ui::datatable
        id="owner-support-tickets"
        :url="route('owner.support.tickets.data')"
        :columns="[
            ['data' => 'ticket', 'title' => __('support::tickets.columns.ticket')],
            ['data' => 'store', 'title' => __('support::tickets.columns.store'), 'orderable' => false],
            ['data' => 'updated', 'title' => __('support::tickets.columns.updated')],
            ['data' => 'status', 'title' => __('support::tickets.columns.status')],
            ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
        ]"
        :order="[[2, 'desc']]"
        :empty-title="__('support::tickets.empty.owner_title')"
        :empty-description="__('support::tickets.empty.owner_description')"
    >
        <x-slot:filters>
            <x-ui::filters-bar target="owner-support-tickets" :search-placeholder="__('support::tickets.filters.search_vendor')">
                <x-ui::select name="status" :label="__('support::tickets.filters.status')" label-hidden :options="['active' => __('support::tickets.tabs.open')] + \App\Modules\Support\Enums\TicketStatus::options()" :placeholder="__('support::tickets.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('support::tickets.filters.status')" class="w-44" />
            </x-ui::filters-bar>
        </x-slot:filters>
    </x-ui::datatable>

    <x-ui::modal id="owner-ticket-modal" :title="__('support::tickets.new_ticket_title')" size="lg">
        @include('support::tickets._remote-states')
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="owner-ticket-form" icon="arrow-up" data-remote-submit>{{ __('support::tickets.thread.send') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>
</section>
