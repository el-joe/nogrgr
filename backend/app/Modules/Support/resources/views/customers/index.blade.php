{{--
    Vendor › Support › Customers (customer ↔ store tickets from the app and the theme
    account): server-side list (search + status) beside the open ticket's conversation
    (shared tickets/thread/_conversation, 15 s polling), reply with files, resolve / close /
    reopen. Without tickets: empty state. $ticket (open one or null), $messages, $canManage.
--}}
<x-vendor::layout :title="$ticket ? $ticket->title() : __('support::customer_tickets.title')" page="vendor-support-customers">
    <div class="grid gap-6" data-support-page>
        <x-ui::page-header :title="__('support::tickets.vendor_title')" :subtitle="__('support::customer_tickets.subtitle')" :breadcrumbs="[__('support::tickets.vendor_title') => vendor_route('support.index'), __('support::customer_tickets.tabs.customers') => null]">
            <x-slot:tabs>
                @include('support::customers._tabs', ['active' => 'customers'])
            </x-slot:tabs>
        </x-ui::page-header>

        @if ($ticket === null)
            <x-ui::card>
                <x-ui::empty-state icon="lifebuoy" :title="__('support::customer_tickets.empty.title')" :description="__('support::customer_tickets.empty.description')" />
            </x-ui::card>
        @else
            <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,380px)_minmax(0,1fr)]">
                <x-ui::datatable
                    id="vendor-customer-tickets"
                    :url="vendor_route('support.customers.data', ['selected' => $ticket->id])"
                    :columns="[
                        ['data' => 'ticket', 'title' => __('support::customer_tickets.list'), 'orderable' => false],
                    ]"
                    :page-length="10"
                    :per-page="[10, 25, 50]"
                    :stack="false"
                    :empty-title="__('support::customer_tickets.empty.title')"
                    :empty-description="__('support::customer_tickets.empty.filtered')"
                >
                    <x-slot:filters>
                        <x-ui::filters-bar target="vendor-customer-tickets" :search-placeholder="__('support::customer_tickets.search')" :chips="false">
                            <x-ui::select name="status" :label="__('support::customer_tickets.status_filter')" label-hidden :options="['active' => __('support::customer_tickets.active')] + \App\Modules\Support\Enums\TicketStatus::options()" :placeholder="__('support::customer_tickets.status_filter')" :search="false" allow-clear data-filter :data-filter-label="__('support::customer_tickets.status_filter')" class="w-full sm:w-40" />
                        </x-ui::filters-bar>
                    </x-slot:filters>
                </x-ui::datatable>

                <div class="grid min-w-0 gap-4">
                    <x-ui::card class="grid gap-1 text-sm">
                        <p class="font-medium text-ink">{{ $ticket->customer?->name }} <span class="text-ink-secondary" dir="ltr">{{ $ticket->customer?->email }}</span></p>
                        <p class="text-ink-secondary">
                            {{ __('support::customer_tickets.topic') }}: {{ __st('support.subjects.'.$ticket->topic->value) }}
                            @if ($ticket->order_number)
                                · {{ __('support::customer_tickets.order', ['number' => $ticket->order_number]) }}
                            @endif
                            · {{ __('support::customer_tickets.opened_at', ['date' => $ticket->created_at?->locale(app()->getLocale())->translatedFormat('j M Y, H:i')]) }}
                        </p>
                    </x-ui::card>
                    @include('support::tickets.thread._conversation', [
                        'ticket' => $ticket,
                        'messages' => $messages,
                        'viewer' => 'store',
                        'title' => $ticket->title(),
                        'messagesUrl' => vendor_route('support.customers.messages', ['ticket' => $ticket->id]),
                        'replyUrl' => $canManage ? vendor_route('support.customers.reply', ['ticket' => $ticket->id]) : null,
                        'realtime' => null,
                        'actions' => view('support::customers._actions', ['ticket' => $ticket, 'canManage' => $canManage]),
                    ])
                </div>
            </div>
        @endif
    </div>
</x-vendor::layout>
