{{--
    Vendor UI kit (VEN-093, decision #116) — the "Tenant Panel language" kit (aligned
    board 191:30163): foundations, form fields, controls, table, overlays & feedback,
    page & card headers and the client components (12:40266), all rendered with the
    shared x-ui components in vendor mode (panels/vendor-shell.css) so the page is the
    live reference of what store pages look like. Light / dark from the top bar or the
    toolbar, English / Arabic (RTL) from the toolbar. Internal only (gate
    vendor.ui-kit.view). Sample records: App\Modules\Platform\Support\UiKitSamples.
--}}
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $sections = [
        'foundations' => __('platform::vendor_ui_kit.sections.foundations'),
        'forms' => __('platform::vendor_ui_kit.sections.forms'),
        'controls' => __('platform::vendor_ui_kit.sections.controls'),
        'table' => __('platform::vendor_ui_kit.sections.table'),
        'overlays' => __('platform::vendor_ui_kit.sections.overlays'),
        'headers' => __('platform::vendor_ui_kit.sections.headers'),
        'components' => __('platform::vendor_ui_kit.sections.components'),
    ];
    // Literal utility classes so Tailwind compiles every swatch (no inline styles).
    $swatches = [
        'bg/page' => 'bg-canvas', 'bg/surface' => 'bg-surface', 'bg/subtle' => 'bg-subtle', 'bg/field' => 'bg-[var(--vnd-field)]',
        'bg/box' => 'bg-[var(--vnd-box)]', 'bg/zebra' => 'bg-[var(--vnd-zebra)]', 'table/head' => 'bg-[var(--vnd-table-head)]', 'bg/inverse' => 'bg-inverse',
        'bg/mobile' => 'bg-[var(--vnd-mobile-canvas)]', 'brand/primary' => 'bg-primary', 'brand/hover' => 'bg-primary-hover', 'brand/pressed' => 'bg-primary-pressed',
        'brand/soft' => 'bg-primary-subtle', 'text/primary' => 'bg-ink', 'text/sub' => 'bg-[var(--vnd-text-sub)]', 'text/faint' => 'bg-[var(--vnd-text-faint)]',
        'text/placeholder' => 'bg-[var(--vnd-placeholder)]', 'border/line' => 'bg-[var(--vnd-table-line)]', 'status/success' => 'bg-success', 'status/success-bg' => 'bg-success-bg',
        'status/pending' => 'bg-[var(--vnd-state-pending)]', 'status/pending-bg' => 'bg-[var(--vnd-state-pending-bg)]', 'status/info' => 'bg-info', 'status/info-bg' => 'bg-info-bg',
        'status/danger' => 'bg-danger', 'status/danger-bg' => 'bg-danger-bg', 'status/required' => 'bg-[var(--vnd-required)]', 'btn/disabled' => 'bg-[var(--vnd-btn-disabled)]',
    ];
    $markets = ['sa' => __('platform::ui-kit.sample.saudi_arabia'), 'ae' => __('platform::ui-kit.sample.uae'), 'eg' => __('platform::ui-kit.sample.egypt'), 'kw' => __('platform::ui-kit.sample.kuwait')];
    $statusOptions = collect(['active', 'draft', 'out_of_stock', 'pending_review'])->mapWithKeys(fn ($status) => [$status => __('ui.status.'.$status)])->all();
@endphp
<x-vendor::layout :title="__('platform::vendor_ui_kit.title')" page="vendor-ui-kit">
    <x-ui::page-header :title="__('platform::vendor_ui_kit.title')" :subtitle="__('platform::vendor_ui_kit.subtitle')">
        <x-slot:actions>
            <div class="flex items-center gap-1 rounded-[8px] bg-[var(--vnd-box)] p-1" role="group" aria-label="{{ __('ui.theme.label') }}">
                <button type="button" data-theme-set="light" aria-pressed="true" class="flex h-9 items-center gap-1.5 rounded-[6px] px-3 text-sm text-ink-secondary aria-pressed:bg-surface aria-pressed:text-ink"><x-ui::icon name="sun" :size="16" />{{ __('ui.theme.light') }}</button>
                <button type="button" data-theme-set="dark" aria-pressed="false" class="flex h-9 items-center gap-1.5 rounded-[6px] px-3 text-sm text-ink-secondary aria-pressed:bg-surface aria-pressed:text-ink"><x-ui::icon name="moon" :size="16" />{{ __('ui.theme.dark') }}</button>
            </div>
            <div class="flex items-center gap-1 rounded-[8px] bg-[var(--vnd-box)] p-1" role="group" aria-label="{{ __('ui.language') }}">
                @foreach ($locales as $code => $language)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" @class(['flex h-9 items-center rounded-[6px] px-3 text-sm', 'bg-surface text-ink' => app()->getLocale() === $code, 'text-ink-secondary hover:text-ink' => app()->getLocale() !== $code]) @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $language['native'] }} · {{ strtoupper($language['dir']) }}</a>
                @endforeach
            </div>
        </x-slot:actions>
        <x-slot:tabs>
            <nav aria-label="{{ __('platform::ui-kit.on_this_page') }}" class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($sections as $key => $label)
                    <a href="#{{ $key }}" class="whitespace-nowrap rounded-[8px] bg-surface px-3 py-1.5 text-sm text-ink-secondary hover:text-primary">{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:tabs>
    </x-ui::page-header>

    <p class="rounded-[12px] bg-primary-subtle px-4 py-3 text-sm text-ink">{{ __('platform::vendor_ui_kit.internal_notice') }}</p>

    {{-- Foundations --}}
    <x-ui::card id="foundations" :title="$sections['foundations']" :subtitle="__('platform::vendor_ui_kit.foundations_hint')">
        <div class="grid gap-6">
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7" role="list">
                @foreach ($swatches as $token => $class)
                    <li class="grid gap-1.5">
                        <span class="h-12 rounded-[8px] border border-[var(--vnd-table-line)] {{ $class }}" aria-hidden="true"></span>
                        <code class="truncate text-xs text-ink-secondary" dir="ltr">{{ $token }}</code>
                    </li>
                @endforeach
            </ul>
            <div class="grid gap-2">
                <p class="text-[32px] font-bold leading-[1.5] text-ink">{{ __('platform::vendor_ui_kit.type.page_title') }} <span class="text-sm font-normal text-ink-muted" dir="ltr">Outfit Bold 32</span></p>
                <p class="text-2xl font-semibold leading-[1.5] text-[var(--vnd-card-title)]">{{ __('platform::vendor_ui_kit.type.card_title') }} <span class="text-sm font-normal text-ink-muted" dir="ltr">SemiBold 24</span></p>
                <p class="text-base text-[var(--vnd-card-sub)]">{{ __('platform::vendor_ui_kit.type.card_subtitle') }} <span class="text-sm text-ink-muted" dir="ltr">Regular 16</span></p>
                <p class="text-base font-medium text-ink">{{ __('platform::vendor_ui_kit.type.button') }} <span class="text-sm font-normal text-ink-muted" dir="ltr">Medium 16</span></p>
                <p class="text-sm text-[var(--vnd-label)]">{{ __('platform::vendor_ui_kit.type.label') }} <span class="text-ink-muted" dir="ltr">Regular 14</span></p>
                <p class="text-xs text-[var(--vnd-text-faint)]">{{ __('platform::vendor_ui_kit.type.note') }} <span dir="ltr">Regular 12</span></p>
                <p class="text-[32px] font-bold leading-[1.5] text-ink" dir="ltr">3,000.00 <span class="text-sm font-normal text-ink-muted">{{ __('platform::vendor_ui_kit.type.kpi_value') }}</span></p>
            </div>
        </div>
    </x-ui::card>

    {{-- Form fields: every state of the filled input (client Frame 224) --}}
    <x-ui::card id="forms" :title="$sections['forms']" :subtitle="__('platform::vendor_ui_kit.forms_hint')">
        <form method="POST" action="{{ vendor_route('ui-kit.form') }}" data-ajax class="grid gap-6" novalidate>
            @csrf
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <x-ui::input name="product_name" :label="__('platform::vendor_ui_kit.fields.product_name')" :placeholder="__('platform::vendor_ui_kit.fields.product_placeholder')" required />
                <x-ui::input name="filled_demo" :label="__('platform::vendor_ui_kit.states.filled')" :value="__('platform::vendor_ui_kit.fields.product_placeholder')" />
                <x-ui::input name="error_demo" :label="__('platform::vendor_ui_kit.states.error')" :error="__('platform::ui-kit.fields.required_message')" required />
                <x-ui::input name="disabled_demo" :label="__('platform::vendor_ui_kit.states.disabled')" :value="__('platform::vendor_ui_kit.fields.product_placeholder')" disabled />
                <x-ui::select name="market" :label="__('platform::vendor_ui_kit.fields.market')" :options="$markets" :placeholder="__('platform::vendor_ui_kit.fields.select_market')" required />
                <x-ui::select name="markets_demo" :label="__('platform::vendor_ui_kit.states.multiple')" :options="$markets" :selected="['sa', 'ae']" multiple />
                <x-ui::select name="select_disabled" :label="__('platform::vendor_ui_kit.states.disabled')" :options="$markets" :placeholder="__('platform::vendor_ui_kit.fields.select_market')" disabled />
                <x-ui::phone name="phone_demo" :label="__('platform::ui-kit.fields.phone')" />
                <x-ui::date name="date_demo" :label="__('platform::ui-kit.fields.start_date')" />
                <x-ui::textarea name="note" :label="__('platform::vendor_ui_kit.fields.note')" :placeholder="__('platform::vendor_ui_kit.fields.note_placeholder')" :maxlength="500" class="md:col-span-2 xl:col-span-3" />
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                <x-ui::button type="reset" variant="secondary">{{ __('platform::ui-kit.reset') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('platform::ui-kit.validate') }}</x-ui::button>
            </div>
        </form>
    </x-ui::card>

    {{-- Controls: checkbox, State pills, tabs, locale tabs, toggles --}}
    <x-ui::card id="controls" :title="$sections['controls']" :subtitle="__('platform::vendor_ui_kit.controls_hint')">
        <div class="grid gap-8">
            <div class="flex flex-wrap items-center gap-6">
                <x-ui::checkbox name="kit_check_off" :label="__('platform::ui-kit.fields.featured')" />
                <x-ui::checkbox name="kit_check_on" :label="__('platform::ui-kit.fields.featured')" checked />
                <x-ui::checkbox name="kit_check_disabled" :label="__('platform::ui-kit.fields.featured')" disabled />
                <x-ui::switch name="kit_switch_on" :label="__('ui.status.active')" checked />
                <x-ui::switch name="kit_switch_off" :label="__('ui.status.active')" />
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach (['delivered', 'processing', 'pending', 'shipped', 'cancelled', 'draft'] as $status)
                    <x-ui::status :status="$status" />
                @endforeach
            </div>
            <x-ui::tabs id="kit-tabs" param="" variant="pill" :items="['orders' => __('platform::vendor_ui_kit.tabs.orders'), 'products' => __('platform::vendor_ui_kit.tabs.products'), 'customers' => __('platform::vendor_ui_kit.tabs.customers')]" active="orders">
                @foreach (['orders', 'products', 'customers'] as $key)
                    <x-ui::tab-panel tabs="kit-tabs" :key="$key" :active="$key === 'orders'" class="pt-3 text-sm text-ink-secondary">{{ __('platform::ui-kit.tabs.panel', ['tab' => __('platform::vendor_ui_kit.tabs.'.$key)]) }}</x-ui::tab-panel>
                @endforeach
            </x-ui::tabs>
            <x-ui::locale-tabs id="kit-i18n">
                @foreach ($locales as $code => $language)
                    <x-ui::locale-pane parent="kit-i18n" :locale="$code">
                        <x-ui::input :name="'kit_title['.$code.']'" :label="__('platform::ui-kit.fields.title_in', ['language' => __('ui.languages.'.$code)])" />
                    </x-ui::locale-pane>
                @endforeach
            </x-ui::locale-tabs>
            <div class="flex flex-wrap items-center gap-3">
                <x-ui::icon-button icon="filter" variant="primary" :label="__('ui.filters.title')" />
                <x-ui::icon-button icon="filter" variant="secondary" :label="__('ui.filters.title')" />
                <x-ui::icon-button icon="more" :label="__('platform::ui-kit.more_actions')" />
            </div>
        </div>
    </x-ui::card>

    {{-- Table (Yajra, server side) + pagination --}}
    <section id="table" class="grid gap-3 rounded-[12px] bg-surface p-4 sm:p-6" aria-labelledby="kit-table-title">
        <h2 id="kit-table-title" class="text-2xl font-semibold leading-[1.5] text-[var(--vnd-card-title)]">{{ $sections['table'] }}</h2>
        <x-ui::datatable
            id="vendor-ui-kit-products"
            :url="vendor_route('ui-kit.table')"
            selectable
            :columns="[
                ['data' => 'name', 'title' => __('platform::ui-kit.table.product')],
                ['data' => 'category', 'title' => __('platform::ui-kit.table.category')],
                ['data' => 'price', 'title' => __('platform::ui-kit.table.price'), 'align' => 'end'],
                ['data' => 'stock', 'title' => __('platform::ui-kit.table.stock'), 'align' => 'end'],
                ['data' => 'status', 'title' => __('platform::ui-kit.table.status')],
                ['data' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false],
            ]"
            :order="[[1, 'asc']]"
        >
            <x-slot:filters>
                <x-ui::filters-bar target="vendor-ui-kit-products" :search-placeholder="__('platform::ui-kit.table.search')">
                    <x-ui::select name="status" :label="__('platform::ui-kit.fields.status')" label-hidden :options="$statusOptions" :placeholder="__('platform::ui-kit.fields.any_status')" :search="false" allow-clear data-filter :data-filter-label="__('platform::ui-kit.fields.status')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
            <x-slot:bulk>
                <x-ui::button variant="ghost" size="sm" icon="check-circle" data-ui-kit-toast="success">{{ __('platform::ui-kit.table.activate') }}</x-ui::button>
            </x-slot:bulk>
        </x-ui::datatable>
        <x-ui::pagination :paginator="$paginator" />
    </section>

    {{-- Overlays & feedback --}}
    <x-ui::card id="overlays" :title="$sections['overlays']" :subtitle="__('platform::vendor_ui_kit.overlays_hint')">
        <div class="grid gap-8">
            <div class="flex flex-wrap items-start gap-3">
                <x-ui::button data-overlay-open="vendor-kit-modal" icon="plus">{{ __('platform::ui-kit.open_modal') }}</x-ui::button>
                <x-ui::button variant="secondary" data-overlay-open="vendor-kit-drawer" icon="filter">{{ __('platform::ui-kit.open_drawer') }}</x-ui::button>
                <x-ui::button variant="secondary" icon="trash" :data-confirm="__('platform::ui-kit.confirm_delete_message')" :data-confirm-title="__('platform::ui-kit.confirm_delete_many')" data-ui-kit-toast="success">{{ __('platform::ui-kit.open_confirm') }}</x-ui::button>
                @foreach (['success', 'error', 'warning', 'info'] as $tone)
                    <x-ui::button variant="ghost" size="sm" :data-ui-kit-toast="$tone">{{ __('platform::vendor_ui_kit.toast_'.$tone) }}</x-ui::button>
                @endforeach
            </div>
            <div class="grid gap-3 lg:grid-cols-3">
                <x-ui::toast tone="success" :title="__('platform::ui-kit.toasts.success_title')" :message="__('platform::ui-kit.toasts.success_message')" static class="pointer-events-auto" />
                <x-ui::toast tone="error" :title="__('platform::ui-kit.toasts.error_title')" :message="__('platform::ui-kit.toasts.error_message')" static class="pointer-events-auto" />
                <x-ui::toast tone="info" :title="__('platform::ui-kit.toasts.info_title')" :message="__('platform::ui-kit.toasts.info_message')" static class="pointer-events-auto" />
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-[12px] bg-subtle">
                    <x-ui::empty-state variant="no-data" icon="return" :title="__('platform::vendor_ui_kit.empty.title')" :description="__('platform::vendor_ui_kit.empty.description')">
                        <x-ui::button :href="vendor_route('ui-kit').'#table'">{{ __('platform::vendor_ui_kit.empty.action') }}</x-ui::button>
                    </x-ui::empty-state>
                </div>
                <div class="grid content-start gap-3 rounded-[12px] bg-subtle p-5">
                    <x-ui::skeleton shape="circle" />
                    <x-ui::skeleton :lines="3" />
                    <x-ui::skeleton shape="rect" width="100%" height="64px" />
                </div>
            </div>
        </div>
    </x-ui::card>

    <x-ui::modal id="vendor-kit-modal" :title="__('platform::vendor_ui_kit.modal.title')" :subtitle="__('platform::vendor_ui_kit.modal.subtitle')" close-on-success>
        <form id="vendor-kit-modal-form" method="POST" action="{{ vendor_route('ui-kit.form') }}" data-ajax class="grid gap-5" novalidate>
            @csrf
            <x-ui::input name="product_name" id="kit-modal-product" :label="__('platform::vendor_ui_kit.fields.product_name')" required data-autofocus />
            <x-ui::select name="market" id="kit-modal-market" :label="__('platform::vendor_ui_kit.fields.market')" :options="$markets" :placeholder="__('platform::vendor_ui_kit.fields.select_market')" required />
        </form>
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="vendor-kit-modal-form">{{ __('ui.save') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>

    <x-ui::drawer id="vendor-kit-drawer" :title="__('ui.filters.title')" :subtitle="__('platform::ui-kit.drawer_subtitle')">
        <div class="grid gap-5">
            <x-ui::select name="drawer_status" :label="__('platform::ui-kit.fields.status')" :options="$statusOptions" multiple />
            <x-ui::select name="drawer_market" :label="__('platform::vendor_ui_kit.fields.market')" :options="$markets" :placeholder="__('platform::ui-kit.fields.all')" />
        </div>
        <x-slot:footer>
            <x-ui::button variant="ghost" data-overlay-close>{{ __('ui.filters.reset') }}</x-ui::button>
            <x-ui::button data-overlay-close>{{ __('ui.filters.apply') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::drawer>

    {{-- Page & card headers --}}
    <section id="headers" class="grid gap-4 rounded-[12px] bg-surface p-4 sm:p-6" aria-labelledby="kit-headers-title">
        <h2 id="kit-headers-title" class="sr-only">{{ $sections['headers'] }}</h2>
        <div class="grid gap-6 md:grid-cols-2">
            <div class="grid gap-1">
                <p class="text-[32px] font-bold leading-[1.5] text-ink">{{ __('platform::vendor_ui_kit.headers.page_title') }}</p>
                <p class="text-sm text-ink-muted">{{ __('platform::vendor_ui_kit.headers.page_subtitle') }}</p>
            </div>
            <div class="grid gap-1">
                <p class="text-2xl font-semibold leading-[1.5] text-[var(--vnd-card-title)]">{{ __('platform::vendor_ui_kit.headers.card_title') }}</p>
                <p class="text-base text-[var(--vnd-card-sub)]">{{ __('platform::vendor_ui_kit.headers.card_subtitle') }}</p>
            </div>
        </div>
    </section>

    {{-- Client components (12:40266): Component 1 buttons, card CTAs, KPI card, charts --}}
    <x-ui::card id="components" :title="$sections['components']" :subtitle="__('platform::vendor_ui_kit.components_hint')">
        <div class="grid gap-8">
            @foreach (['primary', 'secondary', 'ghost', 'danger'] as $variant)
                <div class="flex flex-wrap items-center gap-3">
                    <span class="w-24 text-xs font-medium uppercase text-ink-muted">{{ __('platform::ui-kit.variants.'.$variant) }}</span>
                    @foreach (['sm', 'md', 'lg'] as $size)
                        <x-ui::button :variant="$variant" :size="$size" icon-trailing="chevron-right">{{ __('platform::ui-kit.button') }}</x-ui::button>
                    @endforeach
                    <x-ui::button :variant="$variant" disabled>{{ __('platform::ui-kit.disabled') }}</x-ui::button>
                    <x-ui::button :variant="$variant" loading>{{ __('platform::ui-kit.button') }}</x-ui::button>
                </div>
            @endforeach
            <div class="flex flex-wrap items-center gap-4">
                <a href="#components" class="vnd-cta vnd-cta-primary"><x-ui::icon name="edit" :size="24" />{{ __('platform::vendor_ui_kit.cta.customize') }}</a>
                <a href="#components" class="vnd-cta vnd-cta-outline"><x-ui::icon name="eye" :size="24" />{{ __('platform::vendor_ui_kit.cta.view_store') }}</a>
                <button type="button" class="vnd-card-cta w-auto px-6" data-ui-kit-toast="success"><x-ui::icon name="zap" :size="20" />{{ __('platform::vendor_ui_kit.cta.flash_sale') }}</button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui::kpi-card :label="__('platform::vendor_ui_kit.kpi.orders')" value="264" icon="cart" :delta="12" :comparison="__('platform::vendor_ui_kit.kpi.orders_note')" />
                <x-ui::kpi-card :label="__('platform::vendor_ui_kit.kpi.average')" value="$112.00" icon="wallet" :delta="-3.5" :comparison="__('platform::vendor_ui_kit.kpi.average_note')" />
                <x-ui::kpi-card :label="__('platform::vendor_ui_kit.kpi.outstanding')" value="$1,500.00" icon="clock" :delta="0" :comparison="__('platform::vendor_ui_kit.kpi.outstanding_note')" />
                <x-ui::kpi-card :label="__('platform::vendor_ui_kit.kpi.customers')" value="196" icon="users" :comparison="__('platform::vendor_ui_kit.kpi.customers_note')" />
            </div>
            <div class="grid gap-4 xl:grid-cols-3">
                <x-ui::chart-card id="vendor-kit-line" class="xl:col-span-2" :title="__('platform::ui-kit.sample.line_chart')" :subtitle="__('platform::ui-kit.sample.chart_subtitle')" type="line" :ranges="$lineRanges" range="30d" />
                <x-ui::chart-card id="vendor-kit-donut" :title="__('platform::ui-kit.sample.donut_chart')" type="doughnut" :height="180" total="366" :ranges="['30d' => ['labels' => [__('ui.status.paid'), __('ui.status.pending'), __('ui.status.processing'), __('ui.status.cancelled')], 'series' => [['label' => __('platform::ui-kit.sample.orders'), 'data' => [258, 62, 31, 15], 'tones' => ['success', 'warning', 'info', 'danger']]]]]" />
            </div>
        </div>
    </x-ui::card>
</x-vendor::layout>
