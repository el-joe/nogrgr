{{-- Ecommet footer (Figma 1:573): charcoal block, link columns, brand, grey link bar, copyright. --}}
@props(['columns' => [], 'legal' => [], 'name'])
<footer class="mt-auto bg-sf-footer text-white" aria-label="{{ __st('shell.footer.label') }}">
    <div class="mx-auto grid max-w-[1280px] gap-10 px-6 py-12 md:grid-cols-[1fr_auto]">
        <div class="grid gap-10 sm:grid-cols-3">
            @foreach ($columns as $key => $links)
                <div class="grid content-start gap-4">
                    <h2 class="text-base font-semibold">{{ __st('shell.footer.columns.'.$key) }}</h2>
                    @foreach ($links as $link)<a href="{{ route($link['route']) }}" class="text-sm tracking-wide text-white/90 hover:underline">{{ __st($link['label']) }}</a>@endforeach
                </div>
            @endforeach
        </div>
        <div class="grid content-start gap-3">
            <x-sf::logo tone="light" />
            <x-sf::lang-switch tone="light" />
        </div>
    </div>
    @if ($legal)
        <div class="bg-sf-footer-bar"><nav class="mx-auto flex max-w-[1280px] flex-wrap justify-center gap-x-16 gap-y-2 px-6 py-3 text-sm" aria-label="{{ __st('shell.footer.legal') }}">@foreach ($legal as $link)<a href="{{ route($link['route']) }}" class="hover:underline">{{ __st($link['label']) }}</a>@endforeach</nav></div>
    @endif
    <p class="bg-sf-footer-bottom py-4 text-center text-sm">{{ __st('shell.footer.copyright', ['year' => now()->year, 'name' => $name]) }}</p>
</footer>
