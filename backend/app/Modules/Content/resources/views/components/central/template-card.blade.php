{{--
    Template card (Figma Card 12:41069): 325×268, 1px #C4C4C4 border, radius 16, the
    theme's home page screenshot. Micro-interaction: hover/focus scrolls the long
    screenshot and lifts the card; a caption with name, family and categories slides up.
    No preview link (WEB-004 is P7) — the CTA starts registration.
--}}
@props(['theme', 'eager' => false])
@php
    $slug = (string) $theme->slug;
    $relative = 'resources/images/central/themes/'.$slug.'.webp';
    $image = is_file(base_path($relative)) ? \Illuminate\Support\Facades\Vite::asset($relative) : ($theme->preview_url ?? null);
    $family = $theme->family ?? null;
    $familyLabel = $family instanceof \BackedEnum ? (method_exists($family, 'label') ? $family->label() : \Illuminate\Support\Str::headline($family->value)) : (filled($family) ? \Illuminate\Support\Str::headline((string) $family) : null);
    $categories = collect($theme->categories ?? []);
@endphp
<article {{ $attributes->class(['site-template group relative aspect-[325/268] w-full overflow-hidden rounded-2xl border border-site-border bg-white']) }} tabindex="0" aria-label="{{ $theme->name }}">
    @if ($image)
        <img src="{{ $image }}" alt="{{ __('content::site.templates.preview_alt', ['name' => $theme->name]) }}" width="650" height="1600"
            class="site-template__shot pointer-events-none absolute inset-x-0 top-0 h-auto w-full select-none" @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async" draggable="false">
    @else
        <div class="grid h-full place-items-center bg-site-tint text-primary"><x-ui::icon name="layers" :size="40" /></div>
    @endif
    <div class="site-template__caption absolute inset-x-0 bottom-0 flex flex-col gap-2 bg-gradient-to-t from-site-ink/90 via-site-ink/70 to-transparent px-4 pt-10 pb-4 text-white">
        <div class="flex items-center justify-between gap-2">
            <h3 class="truncate text-lg font-medium">{{ $theme->name }}</h3>
            @if ($familyLabel)
                <span class="shrink-0 rounded-full bg-white/20 px-2.5 py-0.5 text-xs">{{ __('content::site.templates.family', ['family' => $familyLabel]) }}</span>
            @endif
        </div>
        @if ($categories->isNotEmpty())
            <ul class="flex flex-wrap gap-1.5">
                @foreach ($categories->take(3) as $category)
                    <li class="rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-medium text-site-ink">{{ $category->name }}</li>
                @endforeach
            </ul>
        @endif
        @if (\Illuminate\Support\Facades\Route::has('central.register'))
            <a href="{{ route('central.register') }}" class="site-template__cta mt-1 inline-flex h-9 items-center justify-center self-start rounded-[40px] bg-primary px-4 text-sm font-medium text-white hover:bg-primary-hover">{{ __('content::site.templates.start_with', ['name' => $theme->name]) }}</a>
        @endif
    </div>
</article>
