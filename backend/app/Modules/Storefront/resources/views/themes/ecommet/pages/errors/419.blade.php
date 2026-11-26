{{-- 419 page expired on a store host (SF-ALL-004, Figma 114:109361). --}}
@include('storefront::themes.ecommet.pages.errors.code', [
    'code' => '419',
    'primary' => ['label' => __st('errors.refresh'), 'href' => url()->previous() !== url()->current() ? url()->previous() : null],
    'secondary' => ['label' => __st('errors.home'), 'href' => url('/')],
])
