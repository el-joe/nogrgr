{{--
    Return detail (SF-ECO-032, Figma Storefront additions 102:105046 / m 102:105336): status
    steps, conversation (customer / store messages + status changes, evidence), reply with
    photos / video, summary, withdraw (confirm). Reply + withdraw post to the Store API.
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    use Illuminate\Support\Carbon;
    $money = fn ($amount) => AccountOrderPages::money($amount);
    $r = $detail;
    $reached = match ($r['status']) {
        'pending', 'awaiting_info' => 2,
        'approved' => 3,
        'item_received', 'inspected' => 4,
        'refunded', 'exchange_shipped', 'exchanged', 'closed' => 5,
        default => 1,
    };
    $steps = ['requested', 'in_review', 'approved', 'received', $r['type'] === 'exchange' ? 'exchanged' : 'refunded'];
    $messages = collect($r['timeline'])->filter(fn ($event) => filled($event['message']) || $event['status_label']);
    $photos = collect($r['media'])->where('kind', 'photo');
    $videos = collect($r['media'])->where('kind', 'video');
    $stepDates = [$r['created_at'], null, $r['dates']['approved_at'], $r['dates']['received_at'], $r['type'] === 'exchange' ? $r['dates']['exchange_shipped_at'] : $r['dates']['refunded_at']];
    $date = fn (?string $value) => $value ? Carbon::parse($value)->translatedFormat('M j') : null;
    $refundRows = collect($r['refunds'])->map(fn (array $refund) => [
        'reference' => $refund['reference'],
        'amount' => $refund['amount'],
        'status' => $refund['status_label'],
        'tone' => match ($refund['status']) { 'completed' => 'success', 'rejected' => 'danger', default => 'warning' },
        'method' => $refund['method_label'],
        'requested_at' => Carbon::parse($refund['requested_at']),
        'processed_at' => $refund['processed_at'] ? Carbon::parse($refund['processed_at']) : null,
        'rejection_reason' => null,
    ])->all();
@endphp
<x-sf::account.layout :title="$r['reference']" :crumb="__st('returns.title')" active="returns" page="return" :seo="$seo" :back="route('storefront.returns.index')">
    <x-slot:header>
        <div class="grid gap-3">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-lg font-semibold leading-[26px] text-sf-ink lg:text-[22px] lg:leading-[30px]" dir="ltr">{{ $r['reference'] }}</h1>
                <x-sf::order.badge :tone="$r['tone']">{{ $r['status_label'] }}</x-sf::order.badge>
            </div>
            <p class="text-xs leading-4 text-sf-muted">{{ __st('returns.requested_on', ['date' => Carbon::parse($r['created_at'])->translatedFormat('M j, Y')]) }} · <a href="{{ route('storefront.orders.show', $r['order_uuid']) }}" class="text-sf-accent hover:underline" dir="ltr">{{ $r['order_number'] }}</a></p>
            <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-2" aria-label="{{ __st('orders.status') }}">
                @foreach ($steps as $index => $step)
                    <li class="flex items-center gap-1.5" @if ($index + 1 === $reached) aria-current="step" @endif>
                        <x-sf::order.badge :tone="$index < $reached ? 'danger' : 'info'">{{ __st('returns.steps.'.$step) }}@if ($index + 1 <= $reached && ($at = $date($stepDates[$index] ?? null))) · {{ $at }}@endif</x-sf::order.badge>
                        @unless ($loop->last)<x-ui::icon name="chevron-right" :size="12" class="text-sf-muted rtl:-scale-x-100" />@endunless
                    </li>
                @endforeach
            </ol>
        </div>
    </x-slot:header>

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-5">
        <div class="grid min-w-0 gap-4">
            @if ($r['status'] === 'awaiting_info')
                <p class="rounded-lg bg-sf-warning-bg px-4 py-3 text-sm text-sf-ink" role="status" data-return-awaiting>{{ __st('returns.awaiting_info') }}</p>
            @endif
            @if ($r['inspection_label'])
                <p @class(['rounded-lg px-4 py-3 text-sm font-medium', 'bg-sf-success-bg text-sf-success' => $r['inspection_result'] === 'accepted', 'bg-sf-danger-bg text-sf-danger' => $r['inspection_result'] !== 'accepted']) role="status" data-return-inspection>{{ $r['inspection_label'] }}</p>
            @endif
            @if ($refundRows !== [])
                <section class="sf-card grid gap-3 p-4" aria-labelledby="return-refunds" data-return-refunds>
                    <h2 id="return-refunds" class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('returns.refunds') }}</h2>
                    <x-sf::order.refunds :refunds="$refundRows" />
                </section>
            @endif
            <section class="sf-card grid gap-3 p-4" aria-labelledby="return-thread">
                <h2 id="return-thread" class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('returns.conversation') }}</h2>
                <div class="grid gap-1 rounded-lg bg-sf-accent-subtle p-3" data-reveal="fade-up">
                    <p class="text-[11px] leading-4 text-sf-muted">{{ __st('returns.you') }} · {{ Carbon::parse($r['created_at'])->translatedFormat('M j, H:i') }}</p>
                    <p class="whitespace-pre-line text-sm leading-5 text-sf-ink">{{ $r['description'] }}</p>
                    @if ($photos->isNotEmpty() || $videos->isNotEmpty())
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @foreach ($photos as $photo)
                                <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="block size-10 overflow-hidden rounded-lg bg-white"><img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}" width="40" height="40" loading="lazy" class="size-full object-contain bg-media"></a>
                            @endforeach
                            @foreach ($videos as $video)
                                <a href="{{ $video['url'] }}" target="_blank" rel="noopener" class="grid size-10 place-items-center rounded-lg bg-white text-sf-ink" aria-label="{{ $video['name'] }}"><x-ui::icon name="camera" :size="18" /></a>
                            @endforeach
                        </div>
                    @endif
                </div>
                @foreach ($messages as $event)
                    @php($mine = $event['author'] === 'customer')
                    @if (filled($event['message']))
                        <div @class(['grid gap-1 rounded-lg p-3', 'bg-sf-accent-subtle' => $mine, 'bg-[#f5f5f5]' => ! $mine]) data-reveal="fade-up">
                            <p class="text-[11px] leading-4 text-sf-muted">{{ $mine ? __st('returns.you') : __st('returns.store') }} · {{ Carbon::parse($event['created_at'])->translatedFormat('M j, H:i') }}</p>
                            <p class="whitespace-pre-line text-sm leading-5 text-sf-ink">{{ $event['message'] }}</p>
                        </div>
                    @elseif ($event['status_label'])
                        <p class="text-center text-[11px] leading-4 text-sf-muted">{{ __st('returns.status_changed', ['status' => $event['status_label']]) }} · {{ Carbon::parse($event['created_at'])->translatedFormat('M j, H:i') }}</p>
                    @endif
                @endforeach
            </section>

            <section class="sf-card grid gap-3 p-4" aria-labelledby="return-reply">
                <h2 id="return-reply" class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('returns.reply') }}</h2>
                @if ($r['can_reply'])
                    <form method="POST" action="{{ route('store-api.v1.returns.messages.store', $r['id']) }}" enctype="multipart/form-data" class="grid gap-3" data-ajax data-success-url="{{ url()->current() }}" data-evidence-form novalidate>
                        <x-sf::form.field for="sf-reply" error-key="message">
                            <label for="sf-reply" class="sr-only">{{ __st('returns.reply') }}</label>
                            <textarea id="sf-reply" name="message" class="sf-control text-sm min-h-[66px] py-2.5" minlength="10" maxlength="2000" required placeholder="{{ __st('returns.reply_placeholder') }}"></textarea>
                        </x-sf::form.field>
                        <x-sf::order.evidence :compact="true" />
                        <div><x-sf::form.button type="submit" size="sm" :busy="__st('returns.sending')">{{ __st('returns.send_reply') }}</x-sf::form.button></div>
                    </form>
                @else
                    <p class="text-sm text-sf-muted">{{ __st('returns.closed_note') }}</p>
                @endif
            </section>
        </div>

        <aside class="grid gap-3">
            <section class="sf-card grid gap-2 p-4">
                <h2 class="text-lg font-semibold leading-[26px] text-sf-ink">{{ __st('returns.summary') }}</h2>
                <dl class="grid gap-2 text-xs leading-4">
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.order') }}</dt><dd class="font-medium text-sf-ink"><a href="{{ route('storefront.orders.show', $r['order_uuid']) }}" class="hover:underline" dir="ltr">{{ $r['order_number'] }}</a></dd></div>
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.item') }}</dt><dd class="text-end font-medium text-sf-ink">{{ $r['product_name'] }} × {{ $r['quantity'] }}</dd></div>
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.reason') }}</dt><dd class="text-end font-medium text-sf-ink">{{ $r['reason']['name'] }}</dd></div>
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.resolution') }}</dt><dd class="font-medium text-sf-ink">{{ $r['type_label'] }}</dd></div>
                    @if ($r['return_method_label'])
                        <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.return_method') }}</dt><dd class="text-end font-medium text-sf-ink">{{ $r['return_method_label'] }}</dd></div>
                    @endif
                    @if ($r['replacement_label'])
                        <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.replacement') }}</dt><dd class="text-end font-medium text-sf-ink">{{ $r['replacement_label'] }} × {{ $r['quantity'] }}</dd></div>
                    @endif
                    @if ($r['customer_note'])
                        <div class="grid gap-0.5"><dt class="text-sf-muted">{{ __st('returns.customer_note') }}</dt><dd class="whitespace-pre-line break-words text-sf-ink">{{ $r['customer_note'] }}</dd></div>
                    @endif
                    @if ($r['exchange_tracking_number'])
                        <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.exchange_tracking') }}</dt><dd class="font-medium text-sf-ink" dir="ltr">{{ $r['exchange_tracking_number'] }}</dd></div>
                        @if ($r['dates']['exchange_shipped_at'])<div class="text-sf-muted">{{ __st('returns.exchange_shipped_on', ['date' => Carbon::parse($r['dates']['exchange_shipped_at'])->translatedFormat('M j, Y')]) }}</div>@endif
                    @endif
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.refund_amount') }}</dt><dd class="font-medium text-sf-ink">{{ $money($r['refund_amount']) }}</dd></div>
                </dl>
            </section>
            @if ($r['can_withdraw'])
                <button type="button" class="sf-btn sf-btn-danger-outline min-h-9 w-full px-4 text-xs" data-sheet-open="sf-withdraw-return"><x-ui::icon name="x" :size="16" />{{ __st('returns.withdraw') }}</button>
                <p class="text-xs leading-4 text-sf-muted">{{ __st('returns.withdraw_hint') }}</p>
            @endif
        </aside>
    </div>

    @if ($r['can_withdraw'])
        <x-sf::modal id="sf-withdraw-return" :title="__st('returns.withdraw_title')" size="sm">
            <form method="POST" action="{{ route('store-api.v1.returns.withdraw', $r['id']) }}" class="grid gap-4" data-ajax data-success-url="{{ url()->current() }}" novalidate>
                <p class="text-sm text-sf-muted">{{ __st('returns.withdraw_text', ['reference' => $r['reference']]) }}</p>
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="sf-btn sf-btn-outline" data-sheet-close>{{ __st('returns.keep_request') }}</button>
                    <x-sf::form.button type="submit" variant="accent" :busy="__st('returns.withdrawing')">{{ __st('returns.withdraw') }}</x-sf::form.button>
                </div>
            </form>
        </x-sf::modal>
    @endif
</x-sf::account.layout>
