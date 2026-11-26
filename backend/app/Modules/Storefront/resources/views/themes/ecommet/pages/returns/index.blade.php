{{--
    Account › Returns (SF-ECO-031, Figma Storefront additions 102:104664 / m 102:104937):
    status chips filter the list in place (one customer's returns), rows link to the detail.
    Inputs: $rows (AccountReturnPages::list rows), $page, $groups, $seo.
--}}
@php
    use App\Modules\Storefront\Services\Account\AccountOrderPages;
    $money = fn ($amount) => AccountOrderPages::money($amount);
@endphp
<x-sf::account.layout :title="__st('returns.title')" active="returns" page="returns" :seo="$seo">
    @if ($rows)
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __st('returns.filters_label') }}" data-filter-tabs="returns">
            @foreach ($groups as $group)
                <button type="button" role="tab" class="sf-pill-tab font-medium aria-selected:border-sf-cta aria-selected:bg-sf-cta aria-selected:text-sf-on-cta" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-filter="{{ $group }}">{{ __st('returns.tabs.'.$group) }}</button>
            @endforeach
        </div>
    @endif

    <div class="grid gap-4" data-filter-list="returns">
        @forelse ($rows as $return)
            <article class="sf-card grid gap-3 p-4" data-reveal="fade-up" data-filter-item="{{ $return['group'] }}">
                <div class="flex items-start gap-3">
                    <x-sf::order.thumb :src="$return['image_url']" :size="40" />
                    <div class="grid min-w-0 flex-1 gap-0.5">
                        <h2 class="truncate text-sm font-medium leading-5 text-sf-ink"><span dir="ltr">{{ $return['reference'] }}</span> · <span dir="ltr">{{ $return['order_number'] }}</span></h2>
                        <p class="text-xs leading-4 text-sf-muted">{{ __st('returns.reason_date', ['reason' => $return['reason']['name'], 'date' => \Illuminate\Support\Carbon::parse($return['created_at'])->translatedFormat('M d, Y')]) }}</p>
                    </div>
                    <x-sf::order.badge :tone="$return['tone']">{{ $return['status_label'] }}</x-sf::order.badge>
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p class="min-w-0 flex-1 text-xs leading-4 text-sf-muted">@if ($return['last_message']){{ __st('returns.last_note', ['note' => \Illuminate\Support\Str::limit($return['last_message'], 120)]) }}@endif</p>
                    <p class="text-sm font-semibold leading-5 text-sf-ink">{{ __st('returns.refund', ['amount' => $money($return['refund_amount'])]) }}</p>
                </div>
                <div><a href="{{ route('storefront.returns.show', $return['id']) }}" class="sf-btn sf-btn-outline min-h-8 px-4 text-xs">{{ __st('returns.view') }}<x-ui::icon name="chevron-right" :size="16" class="rtl:-scale-x-100" /></a></div>
            </article>
        @empty
            <x-sf::order.empty icon="return" :title="__st('returns.empty_title')" :text="__st('returns.empty_text')" :action="__st('returns.view_orders')" :href="route('storefront.orders.index', ['status' => 'delivered'])" data-reveal="fade-up" />
        @endforelse
        @if ($rows)
            <x-sf::order.empty icon="return" :title="__st('returns.empty_title')" :text="__st('returns.filter_empty')" data-filter-empty hidden />
        @endif
    </div>

    <x-sf::order.pagination :page="$page" />
</x-sf::account.layout>
