{{--
    VEN-009d (Dashboard 12:11267 "New in products"): the newest offered central products
    not in the store yet (VEN-011 data, decision #65: nothing is copied, the store only
    selects), each with preview and "Add to store" (VEN-011 import). Hidden without
    catalog.products.view. Data: $widgets (VendorDashboardWidgetsData).
--}}
@php
    $products = $widgets->newIn;
@endphp
@if ($widgets->failed('new_in'))
    @include('platform::vendor.dashboard._widget-error')
@elseif ($products !== null)
    <section class="grid gap-5" aria-labelledby="new-in-title" data-reveal="fade-up" data-dashboard-widget="new_in" data-added-label="{{ __('platform::dashboard_vendor.widgets.added') }}">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div class="grid max-w-[700px] gap-1">
                <h2 id="new-in-title" class="text-[22px] font-bold leading-[1.4] text-ink sm:text-[26px]">{{ __('platform::dashboard_vendor.new_in.title') }}</h2>
                <p class="text-sm leading-[1.5] text-ink-muted">{{ __('platform::dashboard_vendor.new_in.subtitle') }}</p>
            </div>
            @if (auth('vendor')->user()?->can('catalog.products.manage'))
                <a href="{{ vendor_route('catalog.products.new') }}" class="inline-flex items-center gap-1 text-base text-ink-secondary hover:text-ink">{{ __('platform::dashboard_vendor.widgets.see_all') }}<x-ui::icon name="chevron-right" :size="20" data-flip /></a>
            @endif
        </div>

        @if ($products === [])
            <div class="rounded-card border border-line bg-surface shadow-card">
                <x-ui::empty-state icon="check-circle" compact :title="__('platform::dashboard_vendor.new_in.empty_title')" :description="__('platform::dashboard_vendor.new_in.empty_body')" />
            </div>
        @else
            <div class="vnd-rail" tabindex="0" aria-label="{{ __('platform::dashboard_vendor.new_in.title') }}">
                @foreach ($products as $product)
                    <div class="vnd-rail-item" data-dashboard-card>
                        @include('catalog::vendor.products._new-card', ['product' => $product])
                    </div>
                @endforeach
            </div>

            <x-ui::modal id="new-preview-modal" :title="__('catalog::vendor.new.preview_title')" :subtitle="__('catalog::vendor.new.preview_subtitle')">
                @include('catalog::admin.partials.remote-states', ['error' => __('catalog::vendor.new.load_error')])
                <x-slot:footer>
                    <x-ui::button variant="secondary" data-overlay-close>{{ __('catalog::vendor.close') }}</x-ui::button>
                    @if ($widgets->can('import'))
                        <x-ui::button icon="plus" data-preview-import :data-url="vendor_route('catalog.products.new.import')" data-remote-submit>{{ __('catalog::vendor.new.add') }}</x-ui::button>
                    @endif
                </x-slot:footer>
            </x-ui::modal>
        @endif
    </section>
@endif
