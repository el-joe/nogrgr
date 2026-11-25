{{--
    Central navbar (Figma Navbar 12:42345 / mobile header 12:43747): white pill 1258×64
    (radius 104, shadow 0 2 4 2 rgba(69,69,69,.1)), logo, links (active = Md-20 orange),
    "More+" menu, language switch and Login. Sticky; shrinks + deepens its shadow on
    scroll and hides on scroll-down on phones (resources/js/central/site/header.js).
--}}
@php
    $has = fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
    $is = fn (string ...$names): bool => request()->routeIs(...$names);
    $primary = collect([
        ['central.home', 'home', ['central.home']],
        ['central.templates', 'templates', ['central.templates', 'central.templates.*']],
        ['central.pricing', 'pricing', ['central.pricing']],
        ['central.how-it-works', 'how_it_works', ['central.how-it-works']],
        ['central.contact', 'contact', ['central.contact']],
    ])->filter(fn (array $link): bool => $has($link[0]));
    $more = collect([
        ['central.blog.index', 'blog', ['central.blog.*']],
        ['central.about', 'about', ['central.about']],
        ['central.faqs', 'faqs', ['central.faqs']],
        ['central.terms', 'terms', ['central.terms']],
        ['central.privacy', 'privacy', ['central.privacy']],
    ])->filter(fn (array $link): bool => $has($link[0]));
    $activeMore = $more->first(fn (array $link): bool => $is(...$link[2]));
    $homeUrl = $has('central.home') ? route('central.home') : url('/');
    $loginUrl = $has('central.login') ? route('central.login') : null;
@endphp
<header class="site-header sticky top-0 z-40 lg:pt-4" data-site-header>
    {{-- Desktop pill --}}
    <div class="hidden justify-center px-6 lg:flex">
        <nav class="site-nav flex h-16 w-full max-w-[1258px] items-center justify-between gap-6 rounded-[104px] bg-white ps-5 pe-4" aria-label="{{ __('content::site.nav.main') }}">
            <a href="{{ $homeUrl }}" class="shrink-0 transition-transform duration-300 hover:scale-[1.03]" aria-label="{{ config('app.name') }}">
                <x-ui::logo variant="light" />
            </a>
            <ul class="flex items-center gap-6 text-xl whitespace-nowrap xl:gap-8">
                @foreach ($primary as [$route, $key, $patterns])
                    @php $active = $is(...$patterns); @endphp
                    <li>
                        <a href="{{ route($route) }}" @class(['site-nav-link', 'font-medium text-primary' => $active, 'text-site-ink' => ! $active]) @if ($active) aria-current="page" @endif>{{ __('content::site.nav.'.$key) }}</a>
                    </li>
                @endforeach
                @if ($more->isNotEmpty())
                    <li class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
                        <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true" aria-controls="site-more-menu"
                            @class(['site-nav-link inline-flex items-center', 'font-medium text-primary' => $activeMore, 'text-site-ink' => ! $activeMore])>
                            {{ $activeMore ? __('content::site.nav.'.$activeMore[1]) : __('content::site.nav.more').'+' }}
                        </button>
                        <ul id="site-more-menu" class="absolute start-1/2 top-full z-10 mt-5 grid min-w-48 -translate-x-1/2 gap-1 rounded-2xl bg-white p-2 text-base shadow-[0_8px_40px_rgba(65,37,30,0.12)] rtl:translate-x-1/2" x-show="open" x-cloak
                            x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
                            @foreach ($more as [$route, $key, $patterns])
                                @php $active = $is(...$patterns); @endphp
                                <li><a href="{{ route($route) }}" @class(['block rounded-xl px-4 py-2.5 transition-colors hover:bg-site-tint', 'font-medium text-primary' => $active, 'text-site-ink' => ! $active]) @if ($active) aria-current="page" @endif>{{ __('content::site.nav.'.$key) }}</a></li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            </ul>
            <div class="flex shrink-0 items-center gap-6">
                <x-central::lang-switch />
                @if ($loginUrl)
                    <a href="{{ $loginUrl }}" class="site-btn inline-flex h-10 items-center justify-center rounded-[40px] bg-primary px-8 text-base font-medium text-white hover:bg-primary-hover">{{ __('content::site.nav.login') }}</a>
                @endif
            </div>
        </nav>
    </div>

    {{-- Mobile bar (Figma 12:43747) --}}
    <div class="relative lg:hidden" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
        <div class="site-mobile-bar flex h-14 items-center gap-3 bg-white px-4">
            <a href="{{ $homeUrl }}" aria-label="{{ config('app.name') }}"><x-ui::logo variant="light" /></a>
            <div class="ms-auto flex items-center gap-4">
                <x-central::lang-switch compact />
                <button type="button" class="grid size-10 place-items-center rounded-lg text-site-ink" @click="open = !open" :aria-expanded="open.toString()" aria-controls="site-mobile-menu"
                    :aria-label="open ? @js(__('content::site.nav.close_menu')) : @js(__('content::site.nav.menu'))">
                    <x-ui::icon name="menu" :size="24" x-show="!open" />
                    <x-ui::icon name="x" :size="24" x-show="open" x-cloak />
                </button>
            </div>
        </div>
        <nav id="site-mobile-menu" class="absolute end-0 top-14 w-[min(15rem,100vw)] bg-white px-6 py-4 shadow-[0_12px_32px_rgba(65,37,30,0.12)]" aria-label="{{ __('content::site.nav.main') }}" x-show="open" x-cloak
            x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <ul class="grid gap-1 text-base">
                @foreach ($primary->concat($more) as [$route, $key, $patterns])
                    @php $active = $is(...$patterns); @endphp
                    <li><a href="{{ route($route) }}" @class(['block py-2 transition-colors hover:text-primary', 'font-medium text-primary' => $active, 'text-site-ink' => ! $active]) @if ($active) aria-current="page" @endif>{{ __('content::site.nav.'.$key) }}</a></li>
                @endforeach
            </ul>
            @if ($loginUrl)
                <a href="{{ $loginUrl }}" class="site-btn mt-3 flex h-10 items-center justify-center rounded-[40px] bg-primary text-base font-medium text-white">{{ __('content::site.nav.login') }}</a>
            @endif
        </nav>
    </div>
</header>
