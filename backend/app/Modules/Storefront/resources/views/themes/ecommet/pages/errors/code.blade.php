{{-- Error code page body (500 / 419 / 403): big red code, title, text, primary + secondary actions. Inputs: $code, $primary [label, href|null=reload], $secondary [label, href]. --}}
@extends('storefront::themes.ecommet.pages.errors.layout', ['title' => __st('errors.'.$code.'.title')])

@section('content')
    <p class="text-[96px] font-semibold leading-none tracking-tight text-eco-red lg:text-[120px]" aria-hidden="true">{{ $code }}</p>
    <h1 class="mt-4 text-2xl font-semibold text-sf-ink lg:text-[28px]">{{ __st('errors.'.$code.'.title') }}</h1>
    <p class="text-sm leading-6 text-sf-muted">{{ __st('errors.'.$code.'.body') }}</p>
    <div class="mt-2 flex flex-wrap justify-center gap-3">
        @if ($primary['href'])
            <a href="{{ $primary['href'] }}" class="sf-btn sf-btn-cta mo-press min-w-[120px] rounded-full px-6">{{ $primary['label'] }}</a>
        @else
            <a href="{{ request()->fullUrl() }}" class="sf-btn sf-btn-cta mo-press min-w-[120px] rounded-full px-6">{{ $primary['label'] }}</a>
        @endif
        <a href="{{ $secondary['href'] }}" class="sf-btn sf-btn-outline mo-press min-w-[100px] rounded-full px-6">{{ $secondary['label'] }}</a>
    </div>
@endsection
