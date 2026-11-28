{{--
    Elora v1 phone "Buy together" (m 1:21303): two products side by side on the gradient with
    a "+" between them and, when both are discounted, the shared discount as the big outlined
    figure at both edges. Each card links to its product (no bundle price is invented).
--}}
@props(['cards' => []])
@if (count($cards) >= 2)
    @php($pair = array_slice($cards, 0, 2))
    @php($percent = min((int) ($pair[0]->discount ?? 0), (int) ($pair[1]->discount ?? 0)))
    <section {{ $attributes->class(['v1-together v1-grad-bg']) }} aria-labelledby="v1-together-title">
        @if ($percent > 0)<span class="v1-together-mark v1-together-mark-start" aria-hidden="true" dir="ltr">{{ $percent }}%</span><span class="v1-together-mark v1-together-mark-end" aria-hidden="true" dir="ltr">{{ $percent }}%</span>@endif
        <h2 id="v1-together-title">{{ __st('elora.home.buy_together') }}</h2>
        <div class="v1-together-row">
            @foreach ($pair as $card)
                @if (! $loop->first)<span class="v1-ico v1-ico-plus" aria-hidden="true"></span>@endif
                <article class="v1-pair mo-card">
                    <a href="{{ $card->url }}" class="v1-pair-img">
                        @if ($card->image)<img src="{{ $card->image }}" alt="" width="96" height="96" loading="lazy" decoding="async">@endif
                        @if ($card->badge)
                            <span class="v1-card-badge v1-pair-badge" data-badge="{{ $card->badgeType }}" dir="auto">{{ $card->badge }}</span>
                        @elseif ($card->flash !== null)
                            <span class="v1-pair-sold">{{ __st('elora.card.sold_percent', ['percent' => $card->flashProgress()]) }}</span>
                        @endif
                    </a>
                    <div class="v1-pair-body">
                        <a href="{{ $card->url }}" class="v1-pair-name" dir="auto">{{ $card->name }}</a>
                        @if ($card->weight)<span class="v1-pair-weight" dir="ltr">{{ $card->weight }}</span>@endif
                        <p class="v1-pair-prices"><span>{{ $card->price }}</span>@if ($card->discount)<span class="v1-pair-off">{{ __st('elora.card.off', ['percent' => $card->discount]) }}</span>@endif</p>
                        @if ($card->reviewCount > 0)<p class="v1-pair-rating"><span aria-hidden="true">★</span> {{ number_format($card->rating, 1) }} (+{{ $card->reviewCount }})</p>@endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
