{{-- Elora v1 trust strip (1:15843 dark 74px; m 1:21386 orange, swipeable). --}}
<section {{ $attributes->class(['v1-features']) }} aria-label="{{ __st('elora.features.label') }}">
    <ul class="v1-features-list" role="list">
        @foreach (['lock' => 'privacy', 'credit-card' => 'payments', 'check-circle' => 'recovery', 'truck-tick' => 'free_shipping'] as $icon => $key)
            <li><span class="v1-ico v1-ico-{{ $icon }}" aria-hidden="true"></span>{{ __st('elora.features.'.$key.'.title') }}</li>
        @endforeach
    </ul>
</section>
