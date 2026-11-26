{{-- Elora footer (rebuilt in Elora style, Storefront additions): name, compact columns, copyright. --}}
@props(['columns' => [], 'legal' => [], 'name'])
<footer class="mt-auto bg-sf-footer text-white" aria-label="{{ __st('shell.footer.label') }}">
    <div class="mx-auto grid max-w-[1200px] gap-6 px-6 py-10">
        <x-sf::logo tone="light" />
        @if ($columns)
            <div class="flex flex-wrap gap-x-24 gap-y-6">
                @foreach ($columns as $key => $links)
                    <div class="grid content-start gap-2">
                        <h2 class="text-sm font-medium">{{ __st('shell.footer.columns.'.$key) }}</h2>
                        @foreach ($links as $link)<a href="{{ $link['url'] }}" class="text-xs text-white/90 hover:underline">{{ $link['title'] }}</a>@endforeach
                    </div>
                @endforeach
            </div>
        @endif
        <div class="flex flex-wrap items-center justify-between gap-4 text-xs">
            <p>{{ __st('shell.footer.copyright', ['year' => now()->year, 'name' => $name]) }}</p>
            <div class="flex flex-wrap items-center gap-6">
                @foreach ($legal as $link)<a href="{{ $link['url'] }}" class="hover:underline">{{ $link['title'] }}</a>@endforeach
                <x-sf::lang-switch tone="light" />
            </div>
        </div>
    </div>
</footer>
