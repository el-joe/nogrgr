{{-- Search box; only rendered when the storefront search route exists. --}}
@props(['variant' => 'ecommet'])
@if (Route::has('storefront.search'))
    <form method="GET" action="{{ route('storefront.search') }}" role="search" {{ $attributes }}>
        <label class="sr-only" for="sf-search-{{ $variant }}">{{ __st('shell.search') }}</label>
        @if ($variant === 'ecommet')
            <div class="flex h-10 w-[383px] items-center rounded-full bg-white ps-4 pe-1">
                <input id="sf-search-{{ $variant }}" type="search" name="q" placeholder="{{ __st('shell.search') }}" class="min-w-0 flex-1 bg-transparent text-sm text-sf-ink outline-none">
                <button type="submit" class="grid size-8 place-items-center rounded-full bg-sf-ink text-white" aria-label="{{ __st('shell.search') }}"><x-ui::icon name="search" :size="16" /></button>
            </div>
        @else
            <div class="flex h-[54px] items-center gap-3 rounded-full bg-[#f1f1f1] px-5">
                <x-ui::icon name="search" :size="22" class="text-sf-ink" />
                <input id="sf-search-{{ $variant }}" type="search" name="q" placeholder="{{ __st('shell.search') }}" class="min-w-0 flex-1 bg-transparent text-base text-sf-ink outline-none">
            </div>
        @endif
    </form>
@endif
