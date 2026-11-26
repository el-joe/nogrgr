{{-- Souqify: 419 page expired on a store host (SF-ALL-004, Figma 115:112643). --}}
@include('storefront::themes.souqify.pages.errors.code', [
    'code' => '419',
    'primary' => ['label' => __st('errors.refresh'), 'href' => url()->previous() !== url()->current() ? url()->previous() : null],
    'secondary' => ['label' => __st('errors.home'), 'href' => url('/')],
])
