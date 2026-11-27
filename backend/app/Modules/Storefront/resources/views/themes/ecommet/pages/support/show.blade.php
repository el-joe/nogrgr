{{--
    Account › Support › ticket (customer ↔ store conversation): reference + status, the
    messages (customer / store bubbles with files behind signed links), reply with files and
    "Close ticket" (confirm sheet). Both post to the Store API and reload the page.
    Inputs: $ticket (CustomerTicket), $messages, $seo.
--}}
@php
    use App\Modules\Support\Http\Resources\StoreApi\CustomerTicketResource;
    $tones = ['info' => 'info', 'warning' => 'warning', 'brand' => 'success', 'success' => 'success', 'neutral' => 'info'];
@endphp
<x-sf::account.layout :title="$ticket->displayReference()" :crumb="__st('support.tickets.title')" active="support" page="support-ticket" :seo="$seo" :back="route('storefront.support.index')">
    <x-slot:header>
        <div class="grid gap-1.5">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-lg font-semibold leading-[26px] text-sf-ink lg:text-[22px] lg:leading-[30px]"><span dir="ltr">{{ $ticket->displayReference() }}</span> · {{ $ticket->subject }}</h1>
                <x-sf::order.badge :tone="$tones[$ticket->status->tone()] ?? 'info'">{{ $ticket->status->requesterLabel() }}</x-sf::order.badge>
            </div>
        </div>
    </x-slot:header>

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_280px] lg:gap-5">
        <div class="grid min-w-0 gap-4">
            <section class="sf-card grid gap-3 p-4" aria-labelledby="ticket-thread">
                <h2 id="ticket-thread" class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('support.tickets.conversation') }}</h2>
                <ol class="grid gap-3" role="list">
                    @foreach ($messages as $message)
                        @php($mine = $message->isFromCustomer())
                        @php($data = CustomerTicketResource::message($ticket, $message))
                        <li @class(['grid max-w-[90%] gap-1 rounded-lg p-3', 'justify-self-end bg-sf-accent-subtle' => $mine, 'justify-self-start bg-sf-bg' => ! $mine]) data-reveal="fade-up" data-ticket-message="{{ $data['author'] }}">
                            <p class="text-[11px] leading-4 text-sf-muted">{{ $mine ? __st('support.tickets.you') : $message->author_name.' · '.__st('support.tickets.store') }} · <time datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->translatedFormat('M j, H:i') }}</time></p>
                            @if (filled($message->body))
                                <p class="whitespace-pre-line break-words text-sm leading-5 text-sf-ink">{{ $message->body }}</p>
                            @endif
                            @if ($data['attachments'] !== [])
                                <ul class="mt-1 flex flex-wrap gap-1.5" role="list">
                                    @foreach ($data['attachments'] as $file)
                                        <li>
                                            <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="inline-flex max-w-[220px] items-center gap-1.5 rounded-lg bg-sf-surface px-2.5 py-1.5 text-xs font-medium text-sf-ink hover:underline">
                                                <x-ui::icon :name="$file['is_image'] ? 'image' : 'file'" :size="16" class="shrink-0 text-sf-accent" />
                                                <span class="truncate">{{ $file['name'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="sf-card grid gap-3 p-4" aria-labelledby="ticket-reply">
                <h2 id="ticket-reply" class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('support.tickets.reply') }}</h2>
                @if ($ticket->customerCanReply())
                    <form method="POST" action="{{ route('store-api.v1.support.tickets.reply', $ticket->id) }}" enctype="multipart/form-data" class="grid gap-3" data-ajax data-success-url="{{ url()->current() }}" novalidate x-data="{ files: 0 }">
                        <x-sf::form.field for="sf-ticket-reply" error-key="body">
                            <label for="sf-ticket-reply" class="sr-only">{{ __st('support.tickets.reply') }}</label>
                            <textarea id="sf-ticket-reply" name="body" class="sf-control min-h-[80px] py-2.5 text-sm" maxlength="5000" placeholder="{{ __st('support.tickets.placeholders.reply') }}"></textarea>
                        </x-sf::form.field>
                        <x-sf::form.field error-key="files">
                            <label class="sf-btn sf-btn-outline min-h-9 w-fit cursor-pointer px-3.5 text-xs has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-sf-ink">
                                <x-ui::icon name="upload" :size="16" />
                                <span x-show="files === 0">{{ __st('support.tickets.fields.files') }}</span>
                                <span x-show="files > 0" x-cloak data-template="{{ __st('support.attached', ['count' => '__N__']) }}" x-text="$el.dataset.template.replace('__N__', files)"></span>
                                <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip" class="sr-only" x-on:change="files = $event.target.files.length">
                            </label>
                        </x-sf::form.field>
                        <div><x-sf::form.button type="submit" size="sm" :busy="__st('support.tickets.sending')">{{ __st('support.tickets.send_reply') }}</x-sf::form.button></div>
                    </form>
                @else
                    <p class="text-sm text-sf-muted" data-ticket-closed>{{ __st('support.tickets.closed_note') }}</p>
                @endif
            </section>
        </div>

        <aside class="grid gap-3">
            <section class="sf-card grid gap-2 p-4">
                <h2 class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('support.tickets.details') }}</h2>
                <dl class="grid gap-2 text-xs leading-4">
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('support.tickets.reference') }}</dt><dd class="font-medium text-sf-ink" dir="ltr">{{ $ticket->displayReference() }}</dd></div>
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('support.tickets.fields.topic') }}</dt><dd class="text-end font-medium text-sf-ink">{{ __st('support.subjects.'.$ticket->topic->value) }}</dd></div>
                    @if ($ticket->order_uuid)
                        <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('support.tickets.fields.order') }}</dt><dd class="font-medium text-sf-ink"><a href="{{ route('storefront.orders.show', $ticket->order_uuid) }}" class="hover:underline" dir="ltr">{{ $ticket->order_number }}</a></dd></div>
                    @endif
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('support.tickets.opened') }}</dt><dd class="font-medium text-sf-ink">{{ $ticket->created_at?->translatedFormat('M j, Y') }}</dd></div>
                </dl>
            </section>
            @unless ($ticket->status->isClosed())
                <button type="button" class="sf-btn sf-btn-danger-outline min-h-9 w-full px-4 text-xs" data-sheet-open="sf-close-ticket"><x-ui::icon name="x" :size="16" />{{ __st('support.tickets.close') }}</button>
            @endunless
        </aside>
    </div>

    @unless ($ticket->status->isClosed())
        <x-sf::modal id="sf-close-ticket" :title="__st('support.tickets.close_title')" size="sm">
            <form method="POST" action="{{ route('store-api.v1.support.tickets.close', $ticket->id) }}" class="grid gap-4" data-ajax data-success-url="{{ url()->current() }}" novalidate>
                <p class="text-sm text-sf-muted">{{ __st('support.tickets.close_text', ['reference' => $ticket->displayReference()]) }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="sf-btn sf-btn-outline" data-sheet-close>{{ __st('support.tickets.keep_open') }}</button>
                    <x-sf::form.button type="submit" variant="accent" :busy="__st('support.tickets.closing')">{{ __st('support.tickets.close') }}</x-sf::form.button>
                </div>
            </form>
        </x-sf::modal>
    @endunless
</x-sf::account.layout>
