{{-- Souqify footer (Figma 1:81429): navy block, brand + tagline, columns, blue link bar, copyright. --}}
@props(['columns' => [], 'legal' => [], 'name'])
<footer class="mt-auto bg-sf-footer text-white" aria-label="{{ __('storefront::shell.footer.label') }}">
    <div class="mx-auto grid max-w-[1440px] gap-10 px-8 py-12 md:grid-cols-[minmax(0,320px)_1fr]">
        <div class="grid content-start gap-4">
            <x-sf::logo tone="light" size="md" />
            <x-sf::lang-switch tone="light" />
        </div>
        <div class="grid gap-10 sm:grid-cols-3">
            @foreach ($columns as $key => $links)
                <div class="grid content-start gap-4">
                    <h2 class="text-xl font-medium">{{ __('storefront::shell.footer.columns.'.$key) }}</h2>
                    @foreach ($links as $link)<a href="{{ route($link['route']) }}" class="text-base text-white/70 hover:text-white">{{ __($link['label']) }}</a>@endforeach
                </div>
            @endforeach
        </div>
    </div>
    @if ($legal)
        <div class="bg-sf-footer-bar"><nav class="mx-auto flex max-w-[1440px] flex-wrap justify-center gap-x-16 gap-y-2 px-8 py-3 text-sm" aria-label="{{ __('storefront::shell.footer.legal') }}">@foreach ($legal as $link)<a href="{{ route($link['route']) }}" class="hover:underline">{{ __($link['label']) }}</a>@endforeach</nav></div>
    @endif
    <p class="py-4 text-center text-sm">{{ __('storefront::shell.footer.copyright', ['year' => now()->year, 'name' => $name]) }}</p>
</footer>
