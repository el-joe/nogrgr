{{--
    Elora product trust cards (1:16245 right column, 1:16088 side column, m 1:21580):
    payment methods, secure payment / privacy (opens the "Why choose" popup), delivery
    guarantee, returns. `layout` = grid (2×2) | stack (one column).
--}}
@props(['layout' => 'grid', 'images' => false])
@php
    $badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges();
    $returns = Route::has('storefront.pages.show') ? route('storefront.pages.show', ['slug' => \App\Modules\Storefront\Enums\SystemStorePage::Returns->value]) : null;
@endphp
<div {{ $attributes->class(['grid gap-3', 'sm:grid-cols-2' => $layout === 'grid']) }} data-reveal-stagger>
    @if ($badges)
        <section class="elo-trust elo-trust-pay" data-reveal="fade-up">
            <h3 class="!text-elo-ink !font-normal">{{ __st('elora_pages.product.payment_methods') }}</h3>
            <ul class="flex flex-wrap gap-2" role="list">
                @foreach ($badges as $badge)
                    @if ($images && file_exists(resource_path('images/payments/'.$badge.'.png')))
                        <li class="elo-pay-img"><img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/payments/'.$badge.'.png') }}" alt="{{ __st('shell.payments.'.$badge) }}" height="25" loading="lazy" decoding="async"></li>
                    @else
                        <li class="elo-pay-chip" title="{{ __st('shell.payments.'.$badge) }}">{{ __st('shell.payments.'.$badge) }}</li>
                    @endif
                @endforeach
            </ul>
        </section>
    @endif
    <button type="button" class="elo-trust elo-trust-privacy text-start transition-shadow hover:shadow-md" data-sheet-open="elo-privacy" data-reveal="fade-up">
        <h3 class="w-full"><x-ui::icon name="elo-secured" :size="24" />{{ __st('elora_pages.product.privacy_secure') }}<x-ui::icon name="elo-chevron-right" :size="20" class="ms-auto" /></h3>
        <ul class="grid gap-1.5" role="list">
            <li><x-ui::icon name="elo-check-sm" :size="14" />{{ __st('elora_pages.product.secure_payment') }}</li>
            <li><x-ui::icon name="elo-check-sm" :size="14" />{{ __st('elora_pages.product.privacy_protection') }}</li>
        </ul>
    </button>
    <section class="elo-trust elo-trust-delivery" data-reveal="fade-up">
        <h3><x-ui::icon name="elo-delivery-truck" :size="24" />{{ __st('cart.trust.delivery') }}</h3>
        <ul class="grid gap-1.5" role="list">
            <li><x-ui::icon name="elo-check-sm" :size="14" />{{ __st('cart.trust.guarantee.credit') }}</li>
            <li><x-ui::icon name="elo-check-sm" :size="14" />{{ __st('cart.trust.guarantee.no_delivery') }}</li>
        </ul>
    </section>
    <section class="elo-trust elo-trust-returns" data-reveal="fade-up">
        <h3><x-ui::icon name="elo-delivery-return" :size="24" />{{ __st('elora_pages.product.returns') }}</h3>
        <ul class="grid gap-1.5" role="list">
            <li><x-ui::icon name="elo-check-sm" :size="14" />{{ __st('cart.trust.guarantee.damaged') }}</li>
            @if ($returns)
                <li><a href="{{ $returns }}" class="text-sf-muted underline hover:text-elo-ink">{{ __st('elora_pages.product.returns_policy') }}</a></li>
            @endif
        </ul>
    </section>
</div>
