{{--
    Admin › Support ticket detail (ADM-116a, Figma Admin Panel 80:96112): header with the
    status badge, Impersonate store (vendor profile → audited impersonation) and Resolve;
    live conversation with replies, internal notes and attachments; "Ticket" card with
    Status / Priority / Assignee (saved on change) and Store, Category, Opened, SLA; Files.
    $ticket, $messages, $attachments, $statuses, $priorities, $assignees, $sla, $realtime,
    $canManage, $canImpersonate.
--}}
@php
    $title = $ticket->title();
    $locale = app()->getLocale();
    $store = $ticket->tenant;
@endphp
<x-layouts.panel surface="admin" :title="$title" data-page="admin-support">
    <div class="grid gap-6" data-ticket-detail data-update-url="{{ route('admin.support.tickets.update', $ticket->id) }}">
        <x-ui::page-header
            :title="$title"
            :breadcrumbs="[__('support::tickets.breadcrumb_system') => null, __('support::tickets.title') => route('admin.support.tickets.index'), $ticket->displayReference() => null]"
        >
            <x-slot:status>
                <x-ui::badge :tone="$ticket->status->tone()" data-request-status-badge>{{ $ticket->status->label() }}</x-ui::badge>
            </x-slot:status>
            @if ($canImpersonate || ($canManage && $ticket->status->isActive()))
                <x-slot:actions>
                    @if ($canImpersonate)
                        <x-ui::button variant="secondary" icon="eye" :href="route('admin.vendors.show', ['tenant' => $ticket->tenant_id, 'open' => 'impersonate'])">{{ __('support::tickets.actions.impersonate') }}</x-ui::button>
                    @endif
                    @if ($canManage && $ticket->status->isActive())
                        <x-ui::button icon="check"
                            :data-confirm-url="route('admin.support.tickets.resolve', $ticket->id)"
                            data-confirm-method="POST"
                            :data-confirm-title="__('support::tickets.actions.resolve_title', ['reference' => $ticket->displayReference()])"
                            :data-confirm="__('support::tickets.actions.resolve_message')"
                            :data-confirm-label="__('support::tickets.actions.resolve')"
                            data-confirm-tone="brand">{{ __('support::tickets.actions.resolve') }}</x-ui::button>
                    @endif
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0">
                @include('support::tickets.thread._conversation', [
                    'ticket' => $ticket,
                    'messages' => $messages,
                    'viewer' => 'admin',
                    'messagesUrl' => route('admin.support.tickets.messages', $ticket->id),
                    'replyUrl' => $canManage ? route('admin.support.tickets.reply', $ticket->id) : null,
                    'realtime' => $realtime,
                ])
            </div>

            <div class="grid min-w-0 gap-6">
                <x-ui::card :title="__('support::tickets.ticket_card')" id="ticket-details">
                    <div class="grid gap-4">
                        @if ($canManage)
                            <x-ui::select name="status" id="ticket-status" :label="__('support::tickets.fields.status')" :options="$statuses" :selected="$ticket->status->value" :search="false" data-ticket-field />
                            <x-ui::select name="priority" id="ticket-priority" :label="__('support::tickets.fields.priority')" :options="$priorities" :selected="$ticket->priority->value" :search="false" data-ticket-field />
                            <x-ui::select name="assigned_admin_id" id="ticket-assignee" :label="__('support::tickets.fields.assignee')" :options="$assignees" :selected="$ticket->assigned_admin_id" :placeholder="__('support::tickets.fields.unassigned')" allow-clear data-ticket-field />
                        @endif
                        <dl class="grid gap-3 text-sm">
                            @unless ($canManage)
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-ink-secondary">{{ __('support::tickets.fields.priority') }}</dt>
                                    <dd><x-ui::badge :tone="$ticket->priority->tone()">{{ $ticket->priority->label() }}</x-ui::badge></dd>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <dt class="text-ink-secondary">{{ __('support::tickets.fields.assignee') }}</dt>
                                    <dd class="font-medium text-ink">{{ $ticket->assignee?->name ?? __('support::tickets.fields.unassigned') }}</dd>
                                </div>
                            @endunless
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-secondary">{{ __('support::tickets.details.store') }}</dt>
                                <dd class="truncate font-semibold text-ink">
                                    @if ($store)
                                        <a href="{{ route('admin.vendors.show', $store->id) }}" class="hover:text-ink-brand hover:underline">{{ $store->name }}</a>
                                    @else
                                        {{ __('support::tickets.account_ticket') }}
                                    @endif
                                </dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-secondary">{{ __('support::tickets.details.opened_by') }}</dt>
                                <dd class="truncate font-semibold text-ink">{{ $ticket->opened_by_name }} <span class="font-normal text-ink-secondary">({{ $ticket->opened_by_type->label() }})</span></dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-secondary">{{ __('support::tickets.details.category') }}</dt>
                                <dd class="font-semibold text-ink">{{ $ticket->category->label() }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-secondary">{{ __('support::tickets.details.opened') }}</dt>
                                <dd class="font-semibold text-ink"><time datetime="{{ $ticket->created_at?->toIso8601String() }}">{{ $ticket->created_at?->locale($locale)->translatedFormat('j M H:i') }}</time></dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-secondary">{{ __('support::tickets.details.sla') }}</dt>
                                <dd data-ticket-sla>
                                    @if ($sla)
                                        <x-ui::badge :tone="$sla['tone']">{{ $sla['label'] }}</x-ui::badge>
                                    @else
                                        <span class="text-ink-secondary">{{ __('support::tickets.details.no_sla') }}</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </x-ui::card>

                @include('support::tickets._files', ['attachments' => $attachments, 'url' => fn (int $id): string => route('admin.support.tickets.attachments.show', ['attachment' => $id, 'download' => 1])])
            </div>
        </div>
    </div>
</x-layouts.panel>
