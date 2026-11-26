{{--
    Order success / status (SF-ECO-024, Figma Storefront additions 137:112775 / m 137:112987):
    landing page after checkout and payment return (orders/{uuid}?placed=1). Pending-payment
    variant: amber "Awaiting payment" badge + "Complete payment" (storefront.checkout.pay).
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    $money = fn ($amount) => AccountOrderPages::money($amount);
    $d = $detail;
    $pending = $d['status'] === 'pending_payment';
    $reached = match ($d['status']) {
        'paid', 'processing' => 2,
        'shipped' => 3,
        'delivered', 'completed', 'returned' => 4,
        default => 1,
    };
    $address = $d['shipping_address'] ?? [];
    $shipTo = collect([$address['address_line1'] ?? null, $address['city'] ?? null])->filter()->implode(', ');
    $min = $d['shipping']['delivery_min_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_min_date']) : null;
    $max = $d['shipping']['delivery_max_date'] ? \Illuminate\Support\Carbon::parse($d['shipping']['delivery_max_date']) : null;
    $pay = $pending && Route::has('storefront.checkout.pay') ? route('storefront.checkout.pay', ['order' => $d['uuid']]) : null;
    $firstName = \Illuminate\Support\Str::before(trim((string) ($customer->name ?? '')), ' ') ?: ($customer->name ?? '');
    $shop = Route::has('storefront.home') ? route('storefront.home') : url('/');
@endphp
<x-sf::layout :title="__st('orders.details')" :mobile-title="__st('orders.details')" :back="route('storefront.orders.index')" nav="account" page="order-success" :seo="$seo">
    <div class="mx-auto w-full max-w-[672px] px-4 py-6 sm:px-4 lg:py-16">
        <section class="sf-card flex flex-col items-center gap-4 px-4 py-8 text-center sm:p-10" aria-labelledby="order-success-title" data-order-success>
            <div class="relative grid size-[88px] place-items-center rounded-full motion-safe:animate-[mo-pop_400ms_cubic-bezier(.22,1,.36,1)_both] {{ $pending ? 'bg-sf-warning-bg text-sf-warning' : 'bg-sf-success-bg text-sf-success' }}" aria-hidden="true">
                <x-ui::icon :name="$pending ? 'clock' : 'check-circle'" :size="44" />
                <span class="absolute inset-0 rounded-full border-2 border-current opacity-0 motion-safe:animate-[mo-ring_900ms_cubic-bezier(.22,1,.36,1)_300ms]"></span>
            </div>
            @if ($pending)
                <x-sf::order.badge tone="warning">{{ __st('orders.awaiting_payment') }}</x-sf::order.badge>
            @endif
            <h1 id="order-success-title" class="text-2xl font-semibold leading-8 text-sf-ink sm:text-[28px] sm:leading-9">{{ __st('orders.thank_you', ['name' => $firstName]) }}</h1>
            <p class="text-sm leading-5 text-sf-muted">{{ $pending ? __st('orders.awaiting_text', ['number' => $d['number']]) : __st('orders.confirmed', ['number' => $d['number'], 'email' => $customer->email ?? '']) }}</p>

            <ol class="flex w-full items-start" aria-label="{{ __st('orders.status') }}">
                @foreach (['placed', 'processing', 'shipped', 'delivered'] as $index => $step)
                    @php($done = $index < $reached)
                    <li class="flex min-w-0 flex-1 flex-col items-center gap-1.5" @if ($index + 1 === $reached) aria-current="step" @endif>
                        <span @class(['grid size-6 place-items-center rounded-full', 'bg-sf-accent text-white' => $done, 'border-2 border-sf-border bg-sf-surface' => ! $done])>@if ($done)<x-ui::icon name="check" :size="13" />@endif</span>
                        <span @class(['text-xs leading-4', 'text-sf-ink' => $done, 'text-sf-muted' => ! $done])>{{ __st('orders.steps.'.$step) }}</span>
                    </li>
                @endforeach
            </ol>

            <dl class="grid w-full gap-2 rounded-lg bg-[#f5f5f5] px-4 py-3.5 text-sm leading-5">
                @if ($min)
                    <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.estimated') }}</dt><dd class="font-medium text-sf-ink">{{ $min->translatedFormat('D j') }}@if ($max && ! $max->isSameDay($min)) – {{ $max->translatedFormat('D j M') }}@else {{ $min->translatedFormat('M') }}@endif</dd></div>
                @endif
                <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.ship_to') }}</dt><dd class="truncate font-medium text-sf-ink">{{ $shipTo ?: '—' }}</dd></div>
                <div class="flex gap-3"><dt class="flex-1 text-start text-sf-muted">{{ __st('orders.payment') }}</dt><dd class="font-medium text-sf-ink">{{ collect([$d['payment_label'], $money($d['totals']['grand_total'])])->filter()->implode(' · ') }}</dd></div>
            </dl>

            <ul class="grid w-full gap-4" data-reveal-stagger>
                @foreach ($d['items'] as $item)
                    <li class="flex items-center gap-3 text-start" data-reveal="fade-up">
                        <x-sf::order.thumb :src="$item['image_url']" :size="48" />
                        <span class="grid min-w-0 flex-1 gap-0.5">
                            <span class="truncate text-sm font-medium leading-5 text-sf-ink">{{ $item['name'] }}</span>
                            <span class="text-xs leading-4 text-sf-muted">{{ collect([$item['variant'], '×'.$item['quantity']])->filter()->implode(' · ') }}</span>
                        </span>
                        <span class="shrink-0 text-sm font-medium leading-5 text-sf-ink">{{ $money($item['total']) }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="grid w-full gap-3 sm:grid-cols-2">
                @if ($pay)
                    <a href="{{ $pay }}" class="sf-btn sf-btn-cta sf-btn-lg px-6"><x-ui::icon name="credit-card" :size="18" />{{ __st('orders.complete_payment') }}</a>
                @else
                    <a href="{{ route('storefront.orders.tracking', $d['uuid']) }}" class="sf-btn sf-btn-cta sf-btn-lg px-6"><x-ui::icon name="truck" :size="18" />{{ __st('orders.track') }}</a>
                @endif
                <a href="{{ $shop }}" class="sf-btn sf-btn-outline sf-btn-lg px-6">{{ __st('orders.continue_shopping') }}</a>
            </div>
            <a href="{{ route('storefront.orders.show', $d['uuid']) }}" class="sf-link text-xs">{{ __st('orders.view_details') }}</a>
        </section>
    </div>
</x-sf::layout>
