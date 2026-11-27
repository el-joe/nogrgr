{{--
    Account › Support (customer ↔ store tickets, shared by the app via the Store API): status
    chips (all / active / closed), one card per ticket linking to the conversation, and the
    "New ticket" sheet (topic, subject, message, optional own order, files) that posts to
    store-api.v1.support.tickets.store and opens the new ticket.
    Inputs: $page (paginator of CustomerTicket), $group, $topics, $orders, $seo.
--}}
@php
    use Illuminate\Support\Carbon;
    $tones = ['info' => 'info', 'warning' => 'warning', 'brand' => 'success', 'success' => 'success', 'neutral' => 'info'];
@endphp
<x-sf::account.layout :title="__st('support.tickets.title')" active="support" page="support" :seo="$seo">
    <x-slot:actions>
        <x-sf::form.button icon="plus" size="sm" data-sheet-open="sf-new-ticket">{{ __st('support.tickets.new') }}</x-sf::form.button>
    </x-slot:actions>

    <p class="text-sm text-sf-muted">{{ __st('support.tickets.intro') }}</p>

    <nav class="flex flex-wrap gap-2" aria-label="{{ __st('support.tickets.filters_label') }}">
        @foreach (['all', 'active', 'closed'] as $key)
            <a href="{{ route('storefront.support.index', array_filter(['status' => $key === 'all' ? null : $key])) }}" class="sf-pill-tab font-medium" @if ($group === $key) aria-current="page" @endif>{{ __st('support.tickets.tabs.'.$key) }}</a>
        @endforeach
    </nav>

    <div class="grid gap-3">
        @forelse ($page->items() as $ticket)
            <a href="{{ route('storefront.support.show', $ticket->id) }}" class="sf-card grid gap-2 p-4 transition-shadow hover:shadow-md" data-reveal="fade-up" data-ticket-row>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="grid min-w-0 gap-0.5">
                        <h2 class="truncate text-sm font-medium leading-5 text-sf-ink"><span dir="ltr">{{ $ticket->displayReference() }}</span> · {{ $ticket->subject }}</h2>
                        <p class="text-xs leading-4 text-sf-muted">
                            {{ __st('support.subjects.'.$ticket->topic->value) }}
                            @if ($ticket->order_number) · {{ __st('support.tickets.order', ['number' => $ticket->order_number]) }}@endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($ticket->customer_unread)
                            <x-sf::order.badge tone="danger">{{ __st('support.tickets.unread') }}</x-sf::order.badge>
                        @endif
                        <x-sf::order.badge :tone="$tones[$ticket->status->tone()] ?? 'info'">{{ $ticket->status->requesterLabel() }}</x-sf::order.badge>
                    </div>
                </div>
                @if ($ticket->latestMessage?->body)
                    <p class="line-clamp-2 text-sm leading-5 text-sf-ink">{{ $ticket->latestMessage->body }}</p>
                @endif
                <p class="text-[11px] leading-4 text-sf-muted">{{ __st('support.tickets.updated', ['date' => ($ticket->last_reply_at ?? $ticket->created_at)?->translatedFormat('M j, Y H:i')]) }}</p>
            </a>
        @empty
            <x-sf::order.empty icon="message" :title="__st('support.tickets.empty_title')" :text="$group === 'all' ? __st('support.tickets.empty_text') : __st('support.tickets.filter_empty')" />
        @endforelse
    </div>

    <x-sf::order.pagination :page="$page" />

    <x-sf::modal id="sf-new-ticket" :title="__st('support.tickets.new_title')" size="md">
        <form method="POST" action="{{ route('store-api.v1.support.tickets.store') }}" enctype="multipart/form-data" class="grid gap-4" data-ajax novalidate x-data="{ files: 0 }">
            <x-sf::alert hidden data-form-error>{{ __st('account.fix_fields') }}</x-sf::alert>
            <x-sf::form.field :label="__st('support.tickets.fields.topic')" for="sf-ticket-topic" required error-key="topic">
                <div class="sf-control relative">
                    <select id="sf-ticket-topic" name="topic" required class="appearance-none pe-8">
                        @foreach ($topics as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-ui::icon name="chevron-down" :size="16" class="pointer-events-none absolute end-3 text-sf-muted" />
                </div>
            </x-sf::form.field>
            @if ($orders !== [])
                <x-sf::form.field :label="__st('support.tickets.fields.order')" for="sf-ticket-order" error-key="order_uuid">
                    <div class="sf-control relative">
                        <select id="sf-ticket-order" name="order_uuid" class="appearance-none pe-8">
                            <option value="">{{ __st('support.tickets.no_order') }}</option>
                            @foreach ($orders as $order)
                                <option value="{{ $order['uuid'] }}">{{ $order['number'] }}</option>
                            @endforeach
                        </select>
                        <x-ui::icon name="chevron-down" :size="16" class="pointer-events-none absolute end-3 text-sf-muted" />
                    </div>
                </x-sf::form.field>
            @endif
            <x-sf::form.input name="subject" :label="__st('support.tickets.fields.subject')" :placeholder="__st('support.tickets.placeholders.subject')" maxlength="150" required />
            <x-sf::form.field :label="__st('support.tickets.fields.message')" for="sf-ticket-body" required error-key="body">
                <textarea id="sf-ticket-body" name="body" rows="5" maxlength="5000" required class="sf-control min-h-[120px] resize-y py-3 text-sm" placeholder="{{ __st('support.tickets.placeholders.message') }}"></textarea>
            </x-sf::form.field>
            <x-sf::form.field error-key="files" :helper="__st('support.tickets.files_hint')">
                <label class="flex cursor-pointer items-center justify-center gap-2.5 rounded-sf-input border border-dashed border-sf-border px-4 py-4 text-sm leading-5 text-sf-muted transition-colors hover:border-sf-accent hover:text-sf-ink focus-within:border-sf-accent">
                    <x-ui::icon name="upload" :size="18" class="text-sf-accent" />
                    <span x-show="files === 0">{{ __st('support.tickets.fields.files') }}</span>
                    <span x-show="files > 0" x-cloak data-template="{{ __st('support.attached', ['count' => '__N__']) }}" x-text="$el.dataset.template.replace('__N__', files)"></span>
                    <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" class="sr-only" x-on:change="files = $event.target.files.length">
                </label>
            </x-sf::form.field>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" class="sf-btn sf-btn-outline" data-sheet-close>{{ __st('support.tickets.cancel') }}</button>
                <x-sf::form.button type="submit" :busy="__st('support.tickets.sending')">{{ __st('support.tickets.send') }}</x-sf::form.button>
            </div>
        </form>
    </x-sf::modal>
</x-sf::account.layout>
