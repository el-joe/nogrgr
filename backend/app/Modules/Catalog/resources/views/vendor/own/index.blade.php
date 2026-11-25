{{--
    Vendor › Own products (VEN-016, Tenant Panel Layout 12:13462): welcome header, pill tabs,
    Add product, then sections (New in, Featured, Best sellers) of three cards with
    "See all" (VEN-016a). Empty store → empty state with Add product.
    Data: $sections (section value => Collection<StoreProduct>), $total, $pricing.
--}}
@php
    $canManage = (bool) auth('vendor')->user()?->can('catalog.own-products.manage');
    $actions = $canManage ? view('catalog::vendor.partials._button', ['href' => vendor_route('catalog.own-products.create'), 'icon' => 'plus', 'label' => __('catalog::vendor.own.add'), 'variant' => 'primary'])->render() : null;
@endphp
<x-vendor::layout :title="__('catalog::vendor.own.title')" page="vendor-catalog">
    @include('catalog::vendor.partials._welcome', $actions ? ['actions' => $actions] : [])
    @include('catalog::vendor.partials._tabs', ['active' => 'own'])

    @if ($total === 0)
        <x-ui::card>
            <x-ui::empty-state icon="box" :title="__('catalog::vendor.own.empty_title')" :description="__('catalog::vendor.own.empty_description')">
                @if ($canManage)
                    <x-ui::button icon="plus" :href="vendor_route('catalog.own-products.create')">{{ __('catalog::vendor.own.add') }}</x-ui::button>
                @endif
            </x-ui::empty-state>
        </x-ui::card>
    @else
        @foreach ($sections as $key => $products)
            @php($section = \App\Modules\Catalog\Enums\OwnProductSection::from($key))
            <section class="grid gap-4" aria-labelledby="section-{{ $key }}" data-reveal="fade-up">
                <header class="flex items-center justify-between gap-3">
                    <h2 id="section-{{ $key }}" class="text-xl font-bold text-ink sm:text-2xl">{{ $section->label() }}</h2>
                    @if ($products->isNotEmpty())
                        <a href="{{ vendor_route('catalog.own-products.show', ['section' => $key]) }}" class="inline-flex items-center gap-1 text-sm text-ink-secondary hover:text-ink-brand">{{ __('catalog::vendor.own.see_all') }}<x-ui::icon name="chevron-right" :size="16" data-flip /></a>
                    @endif
                </header>
                @if ($products->isEmpty())
                    <p class="rounded-card bg-surface p-5 text-sm text-ink-muted">{{ __('catalog::vendor.own.section_empty.'.$key) }}</p>
                @else
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,300px),1fr))] gap-6" data-reveal-stagger>
                        @foreach ($products as $product)
                            @include('catalog::vendor.own._card', ['product' => $product, 'price' => $pricing->ownPrice($product)])
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    @endif
</x-vendor::layout>
