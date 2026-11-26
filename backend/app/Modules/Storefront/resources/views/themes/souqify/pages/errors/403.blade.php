{{-- Souqify: 403 on a store host (SF-ALL-004, Figma 115:112667): back home + sign in with another account. --}}
@include('storefront::themes.souqify.pages.errors.code', [
    'code' => '403',
    'primary' => ['label' => __st('errors.back_home'), 'href' => url('/')],
    'secondary' => ['label' => __st('errors.sign_in'), 'href' => Route::has('storefront.account.login') ? route('storefront.account.login') : url('/')],
])
