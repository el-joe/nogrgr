{{-- Store-page section "Category grid" (VEN-032): picked (or top) categories as image tiles, staggered reveal. --}}
@props(['section'])
<section class="grid gap-4" aria-labelledby="ps-{{ $section['key'] }}">
    <x-sf::page-section.heading :title="$section['title'] ?? __st('pages.sections.categories')" :id="'ps-'.$section['key']" />
    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 lg:gap-4" role="list" data-reveal-stagger>
        @foreach ($section['categories'] as $category)
            <li data-reveal="fade-up">
                <a href="{{ route('storefront.category', $category->slug) }}" class="group flex h-full flex-col items-center gap-3 rounded-sf-card border border-sf-border bg-sf-surface p-4 text-center transition-shadow hover:shadow-md">
                    <span class="grid size-24 place-items-center overflow-hidden rounded-full bg-sf-bg">
                        <x-sf::category-image :category="$category" :size="96" class="size-full object-contain transition-transform duration-300 motion-safe:group-hover:scale-105" />
                    </span>
                    <span class="text-sm font-medium leading-5 text-sf-ink">{{ $category->name }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
