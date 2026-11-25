{{--
    Central navbar (Figma Navbar 12:42346, mobile header 125:11865): logo, the marketing
    links that exist (Route::has, no dead links), language switch and Login.
--}}
@php
    $navLinks = collect([
        ['central.home', 'home'],
        ['central.templates', 'templates'],
        ['central.pricing', 'pricing'],
        ['central.how-it-works', 'how_it_works'],
        ['central.contact', 'contact'],
    ])->filter(fn (array $link): bool => \Illuminate\Support\Facades\Route::has($link[0]))->values();
    $locales = \App\Shared\Support\Locale::enabled();
    $currentLocale = app()->getLocale();
    $nextLocale = collect(array_keys($locales))->first(fn (string $code): bool => $code !== $currentLocale);
    $switchUrl = $nextLocale ? request()->fullUrlWithQuery(['lang' => $nextLocale]) : null;
    $homeUrl = \Illuminate\Support\Facades\Route::has('central.home') ? route('central.home') : null;
    $loginUrl = route('central.tenant.login');
@endphp
<header class="relative z-20" x-data="{ open: false }" @keydown.escape.window="open = false">
    {{-- Desktop pill --}}
    <div class="hidden justify-center px-6 pt-4 lg:flex">
        <nav class="flex h-16 w-full max-w-[1258px] items-center justify-between rounded-[104px] bg-white px-5 shadow-(--reg-nav-shadow)" aria-label="{{ __('platform::register.shell.main_nav') }}">
            <a href="{{ $homeUrl ?? $loginUrl }}" class="shrink-0" aria-label="{{ config('app.name') }}">
                <x-ui::logo variant="light" />
            </a>
            <ul class="flex items-center gap-8 text-xl">
                @foreach ($navLinks as [$routeName, $key])
                    <li>
                        <a href="{{ route($routeName) }}" class="text-ink transition-colors hover:text-primary">{{ __('platform::register.shell.'.$key) }}</a>
                    </li>
                @endforeach
            </ul>
            <div class="flex items-center gap-6">
                @if ($switchUrl)
                    <a href="{{ $switchUrl }}" class="inline-flex items-center gap-2 text-xl text-(--reg-gray) transition-colors hover:text-ink" lang="{{ $nextLocale }}" hreflang="{{ $nextLocale }}" aria-label="{{ __('platform::register.shell.switch_language', ['language' => $locales[$nextLocale]['native']]) }}">
                        <x-ui::icon name="globe" :size="24" />
                        <span>{{ $currentLocale === 'ar' ? 'ع' : \Illuminate\Support\Str::ucfirst($currentLocale) }}</span>
                    </a>
                @endif
                <a href="{{ $loginUrl }}" class="inline-flex h-10 items-center justify-center rounded-[40px] bg-primary px-8 text-base font-medium text-white transition hover:bg-primary-hover active:scale-[0.98]">{{ __('platform::register.shell.login') }}</a>
            </div>
        </nav>
    </div>

    {{-- Mobile bar --}}
    <div class="flex h-14 items-center gap-3 px-4 lg:hidden">
        <a href="{{ $homeUrl ?? $loginUrl }}" aria-label="{{ config('app.name') }}"><x-ui::logo variant="light" /></a>
        <div class="ms-auto flex items-center gap-4">
            @if ($switchUrl)
                <a href="{{ $switchUrl }}" class="text-lg text-(--reg-gray)" lang="{{ $nextLocale }}" hreflang="{{ $nextLocale }}" aria-label="{{ __('platform::register.shell.switch_language', ['language' => $locales[$nextLocale]['native']]) }}">{{ $nextLocale === 'ar' ? 'ع' : \Illuminate\Support\Str::ucfirst($nextLocale) }}</a>
            @endif
            <button type="button" class="grid size-9 place-items-center rounded-lg text-ink" @click="open = !open" :aria-expanded="open.toString()" aria-controls="reg-mobile-menu" aria-label="{{ __('platform::register.shell.menu') }}">
                <x-ui::icon name="menu" :size="24" x-show="!open" />
                <x-ui::icon name="x" :size="24" x-show="open" x-cloak />
            </button>
        </div>
    </div>
    <div id="reg-mobile-menu" class="absolute inset-x-4 top-14 rounded-2xl bg-white p-4 shadow-(--reg-card-shadow) lg:hidden" x-show="open" x-cloak
        x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click.outside="open = false">
        <nav aria-label="{{ __('platform::register.shell.main_nav') }}">
            <ul class="grid gap-1 text-base">
                @foreach ($navLinks as [$routeName, $key])
                    <li><a href="{{ route($routeName) }}" class="block rounded-lg px-3 py-2.5 text-ink hover:bg-(--reg-tint)">{{ __('platform::register.shell.'.$key) }}</a></li>
                @endforeach
            </ul>
            <a href="{{ $loginUrl }}" class="mt-3 flex h-11 items-center justify-center rounded-[40px] bg-primary text-base font-medium text-white">{{ __('platform::register.shell.login') }}</a>
        </nav>
    </div>
</header>
