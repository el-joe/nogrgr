{{--
    Footer additions of the store (Vendor › Appearance › Footer / Social links, VEN-034e/f):
    the "about" line and the social profile icons (Figma footers' social row). Each family
    footer includes it; nothing renders when the store set neither.
    <x-sf::footer.social class="…" />
--}}
@php
    $appearance = app(\App\Modules\Storefront\Services\Appearance\StorefrontAppearance::class);
    $about = $appearance->aboutText();
    $social = $appearance->socialLinks();
@endphp
@if ($about || $social)
    <div {{ $attributes->class(['grid gap-4']) }}>
        @if ($about)
            <p class="max-w-[320px] text-sm leading-6 text-white/80">{{ $about }}</p>
        @endif
        @if ($social)
            <ul class="flex flex-wrap items-center gap-2.5" role="list" aria-label="{{ __st('appearance.follow_us') }}">
                @foreach ($social as $profile)
                    <li>
                        <a href="{{ $profile['url'] }}" target="_blank" rel="noopener me" class="grid size-9 place-items-center rounded-full bg-white/10 text-white transition duration-200 hover:-translate-y-0.5 hover:bg-white hover:text-[#121212] motion-reduce:transition-none motion-reduce:hover:translate-y-0" aria-label="{{ $profile['label'] }}" title="{{ $profile['label'] }}">
                            <x-ui::icon :name="$profile['icon']" :size="18" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
