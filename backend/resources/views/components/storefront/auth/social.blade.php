{{-- "or continue with" + Google / Apple — only providers configured in Admin › Integrations (shopper social sign-in). --}}
@props(['redirect' => null])
@php($providers = array_values(array_filter(\App\Modules\Identity\Enums\SocialProvider::enabled(), fn ($provider) => Route::has('storefront.account.social.'.$provider->value))))
@if ($providers)
    <p class="flex items-center gap-3 text-xs text-sf-muted"><span class="h-px flex-1 bg-sf-border"></span>{{ __st('account.or_continue') }}<span class="h-px flex-1 bg-sf-border"></span></p>
    <div @class(['grid gap-3', 'grid-cols-2' => count($providers) > 1]) data-social-login>
        @foreach ($providers as $provider)
            <a href="{{ route('storefront.account.social.'.$provider->value, array_filter(['redirect' => $redirect])) }}" class="sf-btn sf-btn-outline" data-social-provider="{{ $provider->value }}"><x-ui::icon :name="$provider->value" :size="18" />{{ __st('account.social.'.$provider->value) }}</a>
        @endforeach
    </div>
@endif
