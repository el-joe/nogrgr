{{--
    Vendor › Support (VEN-091 + VEN-091b, Figma Vendor Panel (new screens) 88:111731):
    "My tickets" (server-side list, search + status) beside the open ticket's live
    conversation (reply with files, close / reopen). New ticket (VEN-091a, 88:113398)
    opens in a modal. Without tickets: empty state with the New ticket action.
    $ticket (open one or null), $messages, $canManage, $realtime.
--}}
<x-vendor::layout :title="$ticket ? $ticket->title() : __('support::tickets.vendor_title')" page="vendor-support">
    <div class="grid gap-6" data-support-page>
        <x-ui::page-header :title="__('support::tickets.vendor_title')" :breadcrumbs="[__('support::tickets.vendor_title') => null]">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui::button icon="plus" data-remote-modal="support-ticket-modal" :data-url="vendor_route('support.create')">{{ __('support::tickets.new_ticket') }}</x-ui::button>
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @if ($ticket === null)
            <x-ui::card>
                <x-ui::empty-state icon="lifebuoy" :title="__('support::tickets.empty.vendor_title')" :description="__('support::tickets.empty.vendor_description')" />
                <div class="mt-2 flex flex-wrap justify-center gap-3 pb-4">
                    @if ($canManage)
                        <x-ui::button icon="plus" data-remote-modal="support-ticket-modal" :data-url="vendor_route('support.create')">{{ __('support::tickets.new_ticket') }}</x-ui::button>
                    @endif
                    <x-ui::button variant="secondary" icon="file" :href="vendor_route('help.index')">{{ __('navigation.vendor.help_center') }}</x-ui::button>
                </div>
            </x-ui::card>
        @else
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
                <x-ui::datatable
                    id="vendor-support-tickets"
                    :url="vendor_route('support.data', ['selected' => $ticket->id])"
                    :columns="[
                        ['data' => 'ticket', 'title' => __('support::tickets.my_tickets'), 'orderable' => false],
                    ]"
                    :page-length="10"
                    :per-page="[10, 25, 50]"
                    :stack="false"
                    :empty-title="__('support::tickets.empty.vendor_title')"
                    :empty-description="__('support::tickets.empty.filtered')"
                >
                    <x-slot:filters>
                        <x-ui::filters-bar target="vendor-support-tickets" :search-placeholder="__('support::tickets.filters.search_vendor')" :chips="false">
                            <x-ui::select name="status" :label="__('support::tickets.filters.status')" label-hidden :options="['active' => __('support::tickets.tabs.open')] + \App\Modules\Support\Enums\TicketStatus::options()" :placeholder="__('support::tickets.filters.status')" :search="false" allow-clear data-filter :data-filter-label="__('support::tickets.filters.status')" class="w-full sm:w-40" />
                        </x-ui::filters-bar>
                    </x-slot:filters>
                </x-ui::datatable>

                <div class="min-w-0">
                    @include('support::tickets.thread._conversation', [
                        'ticket' => $ticket,
                        'messages' => $messages,
                        'viewer' => 'vendor',
                        'title' => $ticket->title(),
                        'messagesUrl' => vendor_route('support.messages', ['ticket' => $ticket->id]),
                        'replyUrl' => $canManage ? vendor_route('support.reply', ['ticket' => $ticket->id]) : null,
                        'realtime' => $realtime,
                        'actions' => view('support::tickets._requester-actions', [
                            'ticket' => $ticket,
                            'canManage' => $canManage,
                            'closeUrl' => vendor_route('support.close', ['ticket' => $ticket->id]),
                            'reopenUrl' => vendor_route('support.reopen', ['ticket' => $ticket->id]),
                        ]),
                    ])
                </div>
            </div>
        @endif
    </div>

    @if ($canManage)
        <x-ui::modal id="support-ticket-modal" :title="__('support::tickets.new_ticket_title')" size="lg">
            @include('support::tickets._remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="support-ticket-form" icon="arrow-up" data-remote-submit>{{ __('support::tickets.thread.send') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-vendor::layout>
