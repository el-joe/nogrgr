{{-- "or continue with" + Google / Apple — only providers whose routes exist. --}}
@props(['redirect' => null])
@php($providers = array_values(array_filter(['google', 'apple'], fn ($provider) => Route::has('storefront.account.social.'.$provider))))
@if ($providers)
    <p class="flex items-center gap-3 text-xs text-sf-muted"><span class="h-px flex-1 bg-sf-border"></span>{{ __st('account.or_continue') }}<span class="h-px flex-1 bg-sf-border"></span></p>
    <div class="grid gap-3" style="grid-template-columns: repeat({{ count($providers) }}, minmax(0, 1fr))">
        @foreach ($providers as $provider)
            <a href="{{ route('storefront.account.social.'.$provider, array_filter(['redirect' => $redirect])) }}" class="sf-btn sf-btn-outline"><x-ui::icon :name="$provider" :size="18" />{{ __st('account.social.'.$provider) }}</a>
        @endforeach
    </div>
@endif
