{{--
    Ecommet phone category menu (Figma m 1:2979): a side drawer opened from the header hamburger
    (data-sheet-open="sf-cat-drawer"; the link keeps href=/categories for no-JS). Left: top-level
    categories with an active state; right: the selected category's sub-category tiles (image or
    tinted initial) + "shop all". Rendered once from the category tree; no request when opening.
    Shared motion sheet: scrim, scroll lock, focus trap, Esc / scrim / close button, start-side slide (RTL mirrored).
--}}
@php
    $tree = app(\App\Modules\Storefront\Services\Catalog\CategoryCatalog::class)->tree();
@endphp
<div class="mo-sheet lg:hidden" id="sf-cat-drawer" data-sheet data-sheet-mode="side" role="dialog" aria-modal="true" aria-labelledby="sf-cat-drawer-title" hidden>
    <div class="mo-sheet-scrim" data-sheet-close></div>
    <div class="mo-sheet-panel mo-side-panel">
        <div class="flex h-12 shrink-0 items-center justify-between border-b border-eco-stroke px-4">
            <h2 id="sf-cat-drawer-title" class="text-base font-medium text-eco-black">{{ __st('shell.nav.categories') }}</h2>
            <button type="button" data-sheet-close class="grid size-8 place-items-center rounded-full text-eco-black hover:bg-black/5" aria-label="{{ __st('shell.close') }}"><x-ui::icon name="x" :size="20" /></button>
        </div>
        @if ($tree === [])
            <p class="grid flex-1 place-items-center px-6 text-center text-sm text-eco-gray">{{ __st('catalog.categories.empty_body') }}</p>
        @else
            <div class="flex min-h-0 flex-1" data-simple-tabs>
                <ul class="w-[133px] shrink-0 overflow-y-auto border-e border-[#c4c4c4] p-2" role="tablist" aria-orientation="vertical" aria-label="{{ __st('shell.nav.categories') }}">
                    @foreach ($tree as $index => $node)
                        <li class="mb-2">
                            <button type="button" role="tab" class="eco-drawer-cat" id="sf-drw-tab-{{ $node['category']->id }}" aria-controls="sf-drw-panel-{{ $node['category']->id }}" aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                <span class="line-clamp-2">{{ $node['category']->name }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <div class="min-w-0 flex-1 overflow-y-auto p-3">
                    @foreach ($tree as $index => $node)
                        <section id="sf-drw-panel-{{ $node['category']->id }}" role="tabpanel" aria-labelledby="sf-drw-tab-{{ $node['category']->id }}" class="grid content-start gap-4" @if ($index !== 0) hidden @endif>
                            <a href="{{ route('storefront.category', ['slug' => $node['category']->slug]) }}" class="inline-flex items-center gap-1 text-sm font-medium text-eco-black">
                                {{ __st('shell.nav.shop_all', ['category' => $node['category']->name]) }}<x-ui::icon name="arrow-right" :size="16" class="shrink-0 rtl:-scale-x-100" />
                            </a>
                            <div class="grid grid-cols-3 gap-x-2 gap-y-4">
                                @foreach ($node['children'] !== [] ? $node['children'] : [$node['category']] as $child)
                                    <a href="{{ route('storefront.category', ['slug' => $child->slug]) }}" class="eco-cat-tile text-xs tracking-[0.5px] [&_.eco-cat-ph]:size-14 [&_img]:size-14">
                                        <x-sf::category-image :category="$child" :size="56" />
                                        <span class="line-clamp-2">{{ $child->name }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('storefront.category.all') }}" class="flex h-12 shrink-0 items-center justify-center gap-1 border-t border-eco-stroke text-sm font-medium text-eco-black">{{ __st('catalog.categories.title') }}<x-ui::icon name="arrow-right" :size="16" class="rtl:-scale-x-100" /></a>
        @endif
    </div>
</div>
