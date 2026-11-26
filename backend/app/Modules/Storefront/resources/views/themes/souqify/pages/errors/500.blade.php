{{-- Souqify: 500 on a store host (SF-ALL-004, Figma 115:112595 / m(ar) 115:112619). --}}
@include('storefront::themes.souqify.pages.errors.code', [
    'code' => '500',
    'primary' => ['label' => __st('errors.try_again'), 'href' => null],
    'secondary' => ['label' => __st('errors.home'), 'href' => url('/')],
])
