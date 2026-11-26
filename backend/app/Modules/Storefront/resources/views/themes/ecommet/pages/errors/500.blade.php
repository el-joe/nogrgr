{{-- 500 on a store host (SF-ALL-004, Figma 114:109313 / m 114:109337). --}}
@include('storefront::themes.ecommet.pages.errors.code', [
    'code' => '500',
    'primary' => ['label' => __st('errors.try_again'), 'href' => null],
    'secondary' => ['label' => __st('errors.home'), 'href' => url('/')],
])
