{{--
    Owner portal › Support ticket (OWN-009): conversation with the platform team (polling
    refresh), reply with files, close / reopen, ticket facts and files.
    $ticket, $messages, $attachments.
--}}
@php($locale = app()->getLocale())
<x-layouts.panel surface="owner" :title="$ticket->title()" data-page="owner-support">
    <div class="grid gap-6">
        <x-ui::page-header :title="$ticket->title()"
            :breadcrumbs="[__('navigation.owner.support') => route('owner.support'), $ticket->displayReference() => null]">
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="chevron-left" class="rtl:[&_svg]:rotate-180" :href="route('owner.support')">{{ __('support::tickets.actions.back') }}</x-ui::button>
            </x-slot:actions>
        </x-ui::page-header>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0">
                @include('support::tickets.thread._conversation', [
                    'ticket' => $ticket,
                    'messages' => $messages,
                    'viewer' => 'owner',
                    'messagesUrl' => route('owner.support.tickets.messages', $ticket->id),
                    'replyUrl' => route('owner.support.tickets.reply', $ticket->id),
                    'realtime' => null,
                    'actions' => view('support::tickets._requester-actions', [
                        'ticket' => $ticket,
                        'canManage' => true,
                        'closeUrl' => route('owner.support.tickets.close', $ticket->id),
                        'reopenUrl' => route('owner.support.tickets.reopen', $ticket->id),
                    ]),
                ])
            </div>
            <div class="grid min-w-0 gap-6">
                <x-ui::card :title="__('support::tickets.ticket_card')">
                    <dl class="grid gap-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-secondary">{{ __('support::tickets.details.store') }}</dt>
                            <dd class="truncate font-semibold text-ink">{{ $ticket->tenant?->getAttribute('name') ?? __('support::tickets.account_ticket') }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-secondary">{{ __('support::tickets.details.category') }}</dt>
                            <dd class="font-semibold text-ink">{{ $ticket->category->label() }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-secondary">{{ __('support::tickets.fields.priority') }}</dt>
                            <dd><x-ui::badge :tone="$ticket->priority->tone()">{{ $ticket->priority->label() }}</x-ui::badge></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-secondary">{{ __('support::tickets.details.opened_by') }}</dt>
                            <dd class="truncate font-semibold text-ink">{{ $ticket->opened_by_name }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-secondary">{{ __('support::tickets.details.opened') }}</dt>
                            <dd class="font-semibold text-ink"><time datetime="{{ $ticket->created_at?->toIso8601String() }}">{{ $ticket->created_at?->locale($locale)->translatedFormat('j M Y, H:i') }}</time></dd>
                        </div>
                    </dl>
                </x-ui::card>
                @include('support::tickets._files', ['attachments' => $attachments, 'url' => fn (int $id): string => route('owner.support.tickets.attachments.show', ['attachment' => $id, 'download' => 1])])
            </div>
        </div>
    </div>
</x-layouts.panel>
