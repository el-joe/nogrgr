{{--
    Elora v1 footer (Figma 1:15433, phone 1:21387): logo, "Connect with" social icons and
    "We accept" badges at the start; Customer service / Policies / Company info columns of the
    store's pages (plus its own footer links); grey legal bar and dark copyright line. Only
    published pages are linked. Phones: logo, social icons, legal links in two columns.
--}}
@props(['name', 'copyright' => null])
@php
    $pages = collect(app(\App\Modules\Storefront\Services\Pages\StorePageReader::class)->footerLinks())->keyBy('slug');
    $appearance = app(\App\Modules\Storefront\Services\Appearance\StorefrontAppearance::class);
    $link = static fn (string $slug, string $key): ?array => $pages->has($slug) ? ['title' => __st('elora.footer.'.$key, ['name' => $name]), 'url' => $pages[$slug]['url']] : null;
    $columns = [
        'customer_service' => [$link('returns-policy', 'returns'), $link('intellectual-property-policy', 'intellectual_property'), $link('shipping-info', 'shipping_info'), $link('report-suspicious-activity', 'report_activity')],
        'policies' => [$link('shipping-info', 'shipping'), $link('purchase-protection', 'payment'), $link('privacy', 'privacy')],
        'company_info' => [$link('about', 'about'), $link('contact', 'contact'), $link('faq', 'faqs')],
    ];
    foreach ($appearance->footerLinks() as $column => $links) {
        $target = match ($column) { 'service' => 'customer_service', 'help' => 'policies', default => 'company_info' };
        $columns[$target] = [...$columns[$target], ...array_map(static fn (array $item): array => ['title' => $item['title'], 'url' => $item['url']], $links)];
    }
    $columns = array_filter(array_map('array_filter', $columns));
    $support = Route::has('storefront.support.index') ? route('storefront.support.index') : ($pages['contact']['url'] ?? null);
    $legal = array_filter([
        $link('terms', 'terms'),
        $link('privacy', 'privacy_policy'),
        $link('privacy-choices', 'privacy_choices'),
        $support !== null ? ['title' => __st('elora.footer.support'), 'url' => $support] : null,
        $link('faq', 'faq'),
    ]);
    $order = ['facebook' => 0, 'x' => 1, 'instagram' => 2, 'youtube' => 3, 'linkedin' => 4];
    $social = collect($appearance->socialLinks())->sortBy(static fn (array $profile): int => $order[$profile['network']] ?? 9)->values();
    $about = $appearance->aboutText();
    $badges = app(\App\Modules\Storefront\Services\Catalog\StorefrontShell::class)->paymentBadges(wallets: true);
@endphp
<footer class="v1-footer mt-auto" aria-label="{{ __st('shell.footer.label') }}">
    <div class="v1-footer-main">
        <div class="v1-footer-brand">
            <x-sf::logo tone="light" size="lg" class="v1-footer-logo" />
            @if ($about)<p class="max-w-[260px] text-sm leading-6 text-white/80 max-lg:text-center">{{ $about }}</p>@endif
            @if ($social->isNotEmpty())
                <div class="v1-footer-connect">
                    <p>{{ __st('elora.footer.connect', ['name' => $name]) }}</p>
                    <ul class="v1-footer-social" role="list" aria-label="{{ __st('appearance.follow_us') }}">
                        @foreach ($social as $profile)
                            <li>
                                <a href="{{ $profile['url'] }}" target="_blank" rel="noopener me" @class(['v1-social-youtube' => $profile['network'] === 'youtube']) aria-label="{{ $profile['label'] }}" title="{{ $profile['label'] }}">
                                    @if (isset($order[$profile['network']]))
                                        <span class="v1-social-ico v1-social-{{ $profile['network'] }}" aria-hidden="true"></span>
                                    @else
                                        <x-ui::icon :name="$profile['icon']" :size="24" />
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($badges)
                <div class="v1-footer-pay">
                    <p>{{ __st('elora.footer.we_accept') }}</p>
                    <ul role="list">
                        @foreach ($badges as $badge)
                            <li title="{{ __st('shell.payments.'.$badge) }}">
                                @if (in_array($badge, ['visa', 'mastercard', 'applepay', 'fawry'], true))
                                    <img src="{{ \Illuminate\Support\Facades\Vite::asset('resources/images/payments/'.$badge.'.png') }}" alt="{{ __st('shell.payments.'.$badge) }}" width="56" height="37" loading="lazy" decoding="async">
                                @else
                                    <span>{{ __st('shell.payments.'.$badge) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        @if ($columns)
            <div class="v1-footer-columns">
                @foreach ($columns as $key => $links)
                    <div>
                        <h2>{{ __st('elora.footer.'.$key) }}</h2>
                        <ul role="list">
                            @foreach ($links as $item)<li><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></li>@endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    @if ($legal)
        <nav class="v1-footer-legal" aria-label="{{ __st('shell.footer.legal') }}">
            @foreach ($legal as $item)<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@endforeach
        </nav>
    @endif
    <p class="v1-footer-copy">{{ $copyright ?? __st('shell.footer.copyright', ['year' => now()->year, 'name' => $name]) }}</p>
</footer>
