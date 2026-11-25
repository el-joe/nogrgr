{{--
    Central footer (Figma FOOTER 12:41809 desktop, mobile 12:43577 area): optional
    newsletter strip, #F4E7E5 body (logo + about + socials | two link columns | contact
    info) and the #5B5453 copyright bar. Contact info/socials come from $siteChannels
    (view composer) and fall back to the support e-mail.
--}}
@props(['newsletter' => true])
@php
    $has = fn (string $name): bool => \Illuminate\Support\Facades\Route::has($name);
    $columns = [
        collect([['central.home', 'home'], ['central.templates', 'templates'], ['central.home', 'features', '#features'], ['central.pricing', 'pricing'], ['central.how-it-works', 'how_it_works']]),
        collect([['central.blog.index', 'blog'], ['central.contact', 'contact'], ['central.privacy', 'privacy'], ['central.terms', 'terms'], ['central.about', 'about_us'], ['central.faqs', 'faqs']]),
    ];
    $columns = array_map(fn ($column) => $column->filter(fn (array $link): bool => $has($link[0]))->values(), $columns);
    $mobileLinks = collect([['central.contact', 'contact'], ['central.about', 'about_us'], ['central.privacy', 'privacy'], ['central.terms', 'terms']])->filter(fn (array $link): bool => $has($link[0]));
    $channels = $siteChannels ?? app(\App\Modules\Content\Services\CentralSiteService::class)->channels();
    $contacts = collect($channels['contacts'] ?? []);
    if ($contacts->isEmpty() && filled(config('mail.from.address'))) {
        $contacts = collect([['key' => 'email', 'icon' => 'mail', 'value' => (string) config('mail.from.address'), 'href' => 'mailto:'.config('mail.from.address')]]);
    }
    $socials = collect($channels['socials'] ?? [])->filter(fn (array $social): bool => filled($social['url'] ?? null));
    $appName = config('app.name');
@endphp
@if ($newsletter)
    <x-central::newsletter />
@endif
<footer class="site-footer relative bg-site-footer text-site-ink">
    <div class="mx-auto flex max-w-[1264px] flex-col gap-8 px-6 pt-8 pb-10 lg:flex-row lg:justify-center lg:gap-[96px] lg:px-4 lg:pt-[98px] lg:pb-[100px]">
        <div class="flex flex-col gap-6 lg:w-[358px]" data-reveal="fade-up">
            <div class="flex flex-col gap-2">
                <a href="{{ $has('central.home') ? route('central.home') : url('/') }}" class="self-start" aria-label="{{ $appName }}"><x-ui::logo variant="light" /></a>
                <p class="hidden text-base leading-6 lg:block">{{ __('content::site.footer.about', ['app' => $appName]) }}</p>
            </div>
            {{-- Mobile links (Figma mobile footer) --}}
            @if ($mobileLinks->isNotEmpty())
                <nav class="lg:hidden" aria-label="{{ __('content::site.footer.nav') }}">
                    <ul class="grid gap-6 text-base">
                        @foreach ($mobileLinks as [$route, $key])
                            <li><a href="{{ route($route) }}" class="site-link">{{ __('content::site.footer.'.$key) }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
            @if ($socials->isNotEmpty())
                <div class="flex flex-col gap-3">
                    <p class="text-xl font-medium lg:sr-only">{{ __('content::site.footer.follow_us') }}</p>
                    <ul class="flex flex-wrap items-center gap-2 max-lg:gap-2.5">
                        @foreach ($socials as $social)
                            @php $label = __('content::site.networks.'.$social['network']); @endphp
                            <li>
                                <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer" class="site-social block text-primary" aria-label="{{ is_string($label) ? $label : $social['network'] }}">
                                    <x-central::social-icon :network="$social['network']" class="size-8 max-lg:size-10" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <nav class="hidden lg:block" aria-label="{{ __('content::site.footer.nav') }}" data-reveal="fade-up" data-reveal-delay="80">
            <div class="flex gap-[88px] text-base whitespace-nowrap">
                @foreach ($columns as $column)
                    <ul class="flex flex-col gap-3">
                        @foreach ($column as $link)
                            <li><a href="{{ route($link[0]).($link[2] ?? '') }}" class="site-link">{{ __('content::site.footer.'.$link[1]) }}</a></li>
                        @endforeach
                    </ul>
                @endforeach
            </div>
        </nav>
        @if ($contacts->isNotEmpty())
            <div class="hidden flex-col gap-6 lg:flex lg:w-[257px]" data-reveal="fade-up" data-reveal-delay="160">
                <p class="text-xl">{{ __('content::site.footer.contact_info') }}</p>
                <x-central::contact-list :contacts="$contacts" class="gap-3.5 text-base" />
            </div>
        @endif
    </div>
    <div class="flex min-h-[54px] items-center justify-center bg-site-footer-bar px-4 py-3 text-center text-sm text-white lg:text-base">
        {{ __('content::site.footer.rights', ['year' => now()->year, 'app' => $appName]) }}
    </div>
</footer>
