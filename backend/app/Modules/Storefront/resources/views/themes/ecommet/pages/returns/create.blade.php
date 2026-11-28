{{--
    Request a return (SF-ECO-030, Figma Storefront additions 102:105455 / m 102:105748): pick
    lines + quantity, reason, description (10–2000), return / exchange (when the store allows
    exchanges), photos + video within ReturnUploadLimits; reasons that need a video say so.
    Posts multipart to the Store API (AfterSales CustomerReturnService::create), then → returns.
    $options = ReturnOptionsResource array.
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    use Illuminate\Support\Carbon;
    $money = fn ($amount) => AccountOrderPages::money($amount);
    $o = $options;
    $left = collect($o['lines'])->where('returnable', true)->sum('available');
    $videoReasons = collect($o['reasons'])->where('requires_video', true)->pluck('name');
    $orderUuid = $o['order']['uuid'];
    $picked = (int) request()->query('item');
    $methods = $o['return_methods'];
@endphp
<x-sf::account.layout :title="__st('returns.request_title')" :crumb="__st('returns.title')" active="returns" page="return-create" :seo="$seo" :back="route('storefront.orders.show', $orderUuid)">
    <p class="-mt-2 text-xs leading-4 text-sf-muted">{{ __st('returns.order_line', ['number' => $o['order']['number'], 'count' => $left]) }}</p>

    @if (! $o['can_return'])
        <x-sf::order.empty icon="return" :title="__st('returns.blocked_title')" :text="$o['blocked_message']" :action="__st('returns.back_to_order')" :href="route('storefront.orders.show', $orderUuid)" data-reveal="fade-up" />
    @else
        <form method="POST" action="{{ route('store-api.v1.orders.returns.store', $orderUuid) }}" enctype="multipart/form-data" class="sf-card grid gap-4 p-4" data-ajax data-success-url="{{ route('storefront.returns.index') }}" data-return-form data-reveal="fade-up" novalidate>
            <fieldset class="grid gap-3">
                <legend class="sr-only">{{ __st('returns.item') }}</legend>
                @foreach ($o['lines'] as $index => $line)
                    <div @class(['flex items-center gap-3', 'opacity-50' => ! $line['returnable']]) data-return-line>
                        <input type="checkbox" class="sf-check" id="sf-return-line-{{ $index }}" @checked($line['returnable'] && (count($o['lines']) === 1 || $picked === $line['order_item_id'])) @disabled(! $line['returnable']) data-return-line-toggle aria-label="{{ __st('returns.select_item', ['name' => $line['name']]) }}">
                        <input type="hidden" name="items[{{ $index }}][order_item_id]" value="{{ $line['order_item_id'] }}" disabled data-return-line-field>
                        <x-sf::order.thumb :src="$line['image_url']" :size="40" />
                        <label for="sf-return-line-{{ $index }}" class="grid min-w-0 flex-1 cursor-pointer gap-0.5">
                            <span class="truncate text-sm font-medium leading-5 text-sf-ink">{{ $line['name'] }}</span>
                            <span class="text-xs leading-4 text-sf-muted">{{ collect([$line['variant_label'], $money($line['total']), $line['returnable'] ? __st('returns.left', ['count' => $line['available']]) : __st('returns.not_returnable')])->filter()->implode(' · ') }}</span>
                        </label>
                        @if ($line['returnable'])
                            <label class="sr-only" for="sf-return-qty-{{ $index }}">{{ __st('returns.quantity') }}</label>
                            <div class="sf-control text-sm min-h-9 w-[72px] shrink-0 px-2">
                                <select id="sf-return-qty-{{ $index }}" name="items[{{ $index }}][quantity]" class="!h-8" disabled data-return-line-field>
                                    @foreach (range(1, max(1, $line['available'])) as $quantity)
                                        <option value="{{ $quantity }}">{{ $quantity }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    @if ($line['returnable'] && $line['exchange_options'] !== [])
                        <div class="grid gap-1 ps-8" data-return-replacement="{{ $index }}" hidden>
                            <label class="text-xs font-medium text-sf-ink" for="sf-return-replacement-{{ $index }}">{{ __st('returns.replacement') }}</label>
                            <div class="sf-control text-sm min-h-9">
                                <select id="sf-return-replacement-{{ $index }}" name="items[{{ $index }}][replacement_variant_id]" class="!h-8" disabled data-return-replacement-field>
                                    <option value="">{{ __st('returns.same_option') }}</option>
                                    @foreach ($line['exchange_options'] as $option)
                                        <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <p class="text-xs text-sf-muted">{{ __st('returns.same_price_hint') }}</p>
                        </div>
                    @endif
                @endforeach
                <p class="sf-error" data-error-for="items" hidden></p>
                <p class="sf-error" data-return-need-item hidden>{{ __st('returns.need_item') }}</p>
            </fieldset>

            <fieldset class="grid gap-2" data-return-methods>
                <legend class="sf-label mb-1.5">{{ __st('returns.return_method') }}<span class="sf-required" aria-hidden="true">*</span></legend>
                @foreach ($methods as $method)
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-sf-ink"><input type="radio" name="return_method" value="{{ $method['value'] }}" class="sf-radio" required @checked($loop->first)>{{ $method['label'] }}</label>
                @endforeach
                <p class="sf-error" data-error-for="return_method" hidden></p>
            </fieldset>

            <x-sf::form.field :label="__st('returns.reason')" for="sf-return-reason" required error-key="reason_code">
                <div class="sf-control">
                    <select id="sf-return-reason" name="reason_code" required data-return-reason>
                        <option value="">{{ __st('returns.choose_reason') }}</option>
                        @foreach ($o['reasons'] as $reason)
                            <option value="{{ $reason['code'] }}" data-requires-video="{{ $reason['requires_video'] ? '1' : '0' }}">{{ $reason['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </x-sf::form.field>

            <x-sf::form.field :label="__st('returns.description')" for="sf-return-description" required :helper="__st('returns.description_hint')" error-key="description">
                <textarea id="sf-return-description" name="description" class="sf-control text-sm min-h-[60px] py-2.5" minlength="10" maxlength="2000" required placeholder="{{ __st('returns.description_placeholder') }}"></textarea>
            </x-sf::form.field>

            @if ($o['policy']['allow_exchanges'])
                <fieldset class="flex flex-wrap gap-5">
                    <legend class="sr-only">{{ __st('returns.resolution') }}</legend>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-sf-ink"><input type="radio" name="type" value="return" class="sf-radio" checked>{{ __st('returns.type_return') }}</label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-sf-ink"><input type="radio" name="type" value="exchange" class="sf-radio">{{ __st('returns.type_exchange') }}</label>
                </fieldset>
            @else
                <input type="hidden" name="type" value="return">
            @endif

            <x-sf::order.evidence />

            <div class="flex items-start gap-2.5 rounded-lg bg-sf-warning-bg px-3 py-2.5 text-xs leading-4 text-sf-ink">
                <x-ui::icon name="alert-triangle" :size="16" class="shrink-0 text-sf-warning" />
                <p data-video-required-text>{{ $videoReasons->isNotEmpty() ? __st('returns.video_required', ['reasons' => $videoReasons->implode('”, “')]) : __st('returns.upload_limits') }}</p>
            </div>

            <x-sf::form.field :label="__st('returns.customer_note')" for="sf-return-note" error-key="customer_note">
                <textarea id="sf-return-note" name="customer_note" class="sf-control text-sm min-h-[60px] py-2.5" maxlength="1000" placeholder="{{ __st('returns.customer_note_hint') }}"></textarea>
            </x-sf::form.field>

            <div class="grid gap-2 rounded-lg border border-sf-border p-3 text-sm" aria-live="polite" data-return-estimate="{{ route('store-api.v1.orders.returns.estimate', $orderUuid) }}">
                <p class="font-medium text-sf-ink">{{ __st('returns.estimate') }}</p>
                <p class="text-xs text-sf-muted" data-estimate-hint>{{ __st('returns.estimate_choose') }}</p>
                <p class="text-xs text-sf-muted" data-estimate-unpaid hidden>{{ __st('returns.estimate_unpaid') }}</p>
                <dl class="grid gap-1.5" data-estimate-rows hidden>
                    <div class="flex gap-3"><dt class="flex-1 text-sf-muted">{{ __st('returns.estimate_items') }}</dt><dd class="text-sf-ink" data-estimate="items"></dd></div>
                    <div class="flex gap-3" data-estimate-row="shipping"><dt class="flex-1 text-sf-muted">{{ __st('returns.estimate_shipping') }}</dt><dd class="text-sf-ink" data-estimate="shipping"></dd></div>
                    <div class="flex gap-3" data-estimate-row="return_fee"><dt class="flex-1 text-sf-muted">{{ __st('returns.estimate_fee') }}</dt><dd class="text-sf-ink">− <span data-estimate="return_fee"></span></dd></div>
                    <div class="flex gap-3 border-t border-sf-border pt-1.5 font-semibold"><dt class="flex-1 text-sf-ink">{{ __st('returns.estimate_total') }}</dt><dd class="text-sf-ink" data-estimate="total"></dd></div>
                    <p class="text-xs text-sf-success" data-estimate-waived hidden>{{ __st('returns.estimate_waived') }}</p>
                    <p class="text-xs text-sf-muted">{{ __st('returns.estimate_hint') }}</p>
                </dl>
            </div>

            @if ($o['policy']['deadline'] || $o['policy']['return_shipping_payer_label'])
                <p class="text-xs leading-4 text-sf-muted">
                    @if ($o['policy']['deadline']){{ __st('returns.window', ['days' => $o['policy']['window_days'], 'date' => Carbon::parse($o['policy']['deadline'])->translatedFormat('M j, Y')]) }}@endif
                    {{ __st('returns.shipping_payer', ['payer' => $o['policy']['return_shipping_payer_label']]) }}
                </p>
            @endif

            @if (filled($o['policy']['text'] ?? null))
                <p class="whitespace-pre-line text-xs leading-5 text-sf-muted" data-store-return-policy>{{ $o['policy']['text'] }}</p>
            @endif

            <div><x-sf::form.button type="submit" size="lg" :busy="__st('returns.sending')">{{ __st('returns.submit') }}</x-sf::form.button></div>
        </form>
    @endif
</x-sf::account.layout>
