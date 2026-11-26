{{--
    Central footer (Figma FOOTER 12:42562 desktop / mobile 125:11865): logo + about,
    the site links that exist (Route::has), contact e-mail and the copyright bar.
--}}
@php
    $footerLinks = collect([
        ['central.home', 'home'],
        ['central.templates', 'templates'],
        ['central.pricing', 'pricing'],
        ['central.how-it-works', 'how_it_works'],
        ['central.blog', 'blog'],
        ['central.about', 'about'],
        ['central.contact', 'contact'],
        ['central.privacy', 'privacy'],
        ['central.terms', 'terms'],
    ])->filter(fn (array $link): bool => \Illuminate\Support\Facades\Route::has($link[0]))->values();
    $supportEmail = app(\App\Modules\Platform\Services\OwnerSupportService::class)->supportEmail();
@endphp
<footer class="relative mt-auto bg-(--reg-footer) text-ink">
    <div class="mx-auto grid max-w-[1200px] gap-8 px-4 pt-8 pb-10 sm:px-6 lg:grid-cols-[358px_1fr_257px] lg:gap-[104px] lg:pt-[98px] lg:pb-[120px]">
        <div class="grid content-start gap-6">
            <x-ui::logo variant="light" />
            <p class="max-w-[358px] text-base leading-6 max-lg:hidden lg:text-xl lg:leading-8">{{ __('platform::register.shell.about_text') }}</p>
        </div>
        @if ($footerLinks->isNotEmpty())
            <nav aria-label="{{ __('platform::register.shell.footer_nav') }}">
                <ul class="grid gap-4 text-base lg:grid-cols-2 lg:gap-x-[104px] lg:text-xl">
                    @foreach ($footerLinks as [$routeName, $key])
                        <li><a href="{{ route($routeName) }}" class="transition-colors hover:text-primary">{{ __('platform::register.shell.'.$key) }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        @if ($supportEmail !== '')
            <div class="grid content-start gap-4 lg:gap-6">
                <p class="text-xl font-medium lg:text-2xl">{{ __('platform::register.shell.contact_info') }}</p>
                <a href="mailto:{{ $supportEmail }}" class="flex items-center gap-2 text-base transition-colors hover:text-primary lg:text-xl" dir="ltr">
                    <x-ui::icon name="mail" :size="24" class="text-primary" />{{ $supportEmail }}
                </a>
            </div>
        @endif
    </div>
    <div class="flex min-h-[54px] items-center justify-center bg-(--reg-footer-bar) px-4 text-center text-sm text-white lg:text-xl">
        {{ __('platform::register.shell.rights', ['year' => now()->year, 'name' => config('app.name')]) }}
    </div>
</footer>
