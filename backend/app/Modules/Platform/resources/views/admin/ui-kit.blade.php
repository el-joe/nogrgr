{{--
    Component library (/admin/ui-kit): every x-ui::* component and state from the Figma
    Admin Panel page (23:10147). Switch light/dark and English/Arabic (RTL) from the
    toolbar or the topbar. Sample records come from App\Modules\Platform\Support\UiKitSamples.
--}}
@php
    $locales = \App\Shared\Support\Locale::enabled();
    $sections = [
        'foundations' => __('platform::ui-kit.sections.foundations'),
        'icons' => __('platform::ui-kit.sections.icons'),
        'buttons' => __('platform::ui-kit.sections.buttons'),
        'forms' => __('platform::ui-kit.sections.forms'),
        'selection' => __('platform::ui-kit.sections.selection'),
        'display' => __('platform::ui-kit.sections.display'),
        'feedback' => __('platform::ui-kit.sections.feedback'),
        'overlays' => __('platform::ui-kit.sections.overlays'),
        'table' => __('platform::ui-kit.sections.table'),
    ];
    $swatches = ['primary', 'primary-hover', 'primary-pressed', 'primary-subtle', 'canvas', 'surface', 'subtle', 'muted', 'inverse', 'ink', 'ink-secondary', 'ink-muted', 'line', 'line-strong', 'success', 'success-bg', 'warning', 'warning-bg', 'danger', 'danger-bg', 'info', 'info-bg', 'sidebar', 'sidebar-hover'];
    $categories = ['electronics' => __('platform::ui-kit.sample.electronics'), 'fashion' => __('platform::ui-kit.sample.fashion'), 'home' => __('platform::ui-kit.sample.home'), 'beauty' => __('platform::ui-kit.sample.beauty')];
    $countries = ['sa' => __('platform::ui-kit.sample.saudi_arabia'), 'ae' => __('platform::ui-kit.sample.uae'), 'eg' => __('platform::ui-kit.sample.egypt'), 'kw' => __('platform::ui-kit.sample.kuwait')];
    $statusOptions = collect(['active', 'draft', 'out_of_stock', 'pending_review'])->mapWithKeys(fn ($status) => [$status => __('ui.status.'.$status)])->all();
@endphp
<x-layouts.panel surface="admin" :title="__('platform::ui-kit.title')" data-page="ui-kit">
    <x-ui::page-header
        :title="__('platform::ui-kit.title')"
        :subtitle="__('platform::ui-kit.subtitle')"
        :breadcrumbs="[__('navigation.sections.system') => null, __('navigation.admin.system_tools') => null, __('platform::ui-kit.title') => null]"
    >
        <x-slot:actions>
            <x-ui::button variant="secondary" icon="sparkles" :href="route('admin.ui-kit.storefront-motion')">{{ __('platform::motion-demo.title') }}</x-ui::button>
            <x-ui::button variant="secondary" icon="cart" :href="route('admin.ui-kit.checkout.show')">{{ __('platform::checkout-demo.title') }}</x-ui::button>
            <div class="flex items-center gap-1 rounded-control bg-subtle p-1" role="group" aria-label="{{ __('ui.theme.label') }}">
                <button type="button" data-theme-set="light" aria-pressed="true" class="flex h-8 items-center gap-1.5 rounded-chip px-3 text-[13px] text-ink-secondary aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-card"><x-ui::icon name="sun" :size="16" />{{ __('ui.theme.light') }}</button>
                <button type="button" data-theme-set="dark" aria-pressed="false" class="flex h-8 items-center gap-1.5 rounded-chip px-3 text-[13px] text-ink-secondary aria-pressed:bg-surface aria-pressed:text-ink aria-pressed:shadow-card"><x-ui::icon name="moon" :size="16" />{{ __('ui.theme.dark') }}</button>
            </div>
            <div class="flex items-center gap-1 rounded-control bg-subtle p-1" role="group" aria-label="{{ __('ui.language') }}">
                @foreach ($locales as $code => $language)
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" lang="{{ $code }}" @class(['flex h-8 items-center rounded-chip px-3 text-[13px]', 'bg-surface text-ink shadow-card' => app()->getLocale() === $code, 'text-ink-secondary hover:text-ink' => app()->getLocale() !== $code]) @if (app()->getLocale() === $code) aria-current="true" @endif>{{ $language['native'] }} · {{ strtoupper($language['dir']) }}</a>
                @endforeach
            </div>
        </x-slot:actions>
        <x-slot:tabs>
            <nav aria-label="{{ __('platform::ui-kit.on_this_page') }}" class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($sections as $key => $label)
                    <a href="#{{ $key }}" class="whitespace-nowrap rounded-full border border-line bg-surface px-3 py-1 text-[13px] text-ink-secondary hover:border-primary hover:text-ink-brand">{{ $label }}</a>
                @endforeach
            </nav>
        </x-slot:tabs>
    </x-ui::page-header>

    {{-- Foundations --}}
    <x-ui::card id="foundations" :title="$sections['foundations']" :subtitle="__('platform::ui-kit.foundations_hint')">
        <div class="grid gap-6">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                @foreach ($swatches as $swatch)
                    <div class="grid gap-1.5">
                        <span class="h-12 rounded-control border border-line" @style(['background-color: var(--color-'.$swatch.')'])></span>
                        <code class="truncate text-xs text-ink-secondary" dir="ltr">{{ $swatch }}</code>
                    </div>
                @endforeach
            </div>
            <div class="grid gap-2">
                <p class="text-[32px] font-semibold leading-10">{{ __('platform::ui-kit.type.display') }} 32/40</p>
                <p class="text-2xl font-semibold leading-8">{{ __('platform::ui-kit.type.h1') }} 24/32</p>
                <p class="text-xl font-semibold leading-7">{{ __('platform::ui-kit.type.h2') }} 20/28</p>
                <p class="text-base font-semibold">{{ __('platform::ui-kit.type.h3') }} 16/24</p>
                <p class="text-sm">{{ __('platform::ui-kit.type.body') }} 14/20</p>
                <p class="text-[13px] leading-[18px] text-ink-secondary">{{ __('platform::ui-kit.type.small') }} 13/18</p>
                <p class="text-xs text-ink-muted">{{ __('platform::ui-kit.type.caption') }} 12/16</p>
                <p class="text-[11px] font-semibold uppercase tracking-[0.06em] text-ink-secondary">{{ __('platform::ui-kit.type.overline') }} 11/16</p>
            </div>
            <div class="flex flex-wrap gap-4">
                @foreach (['chip' => 'rounded-chip', 'control' => 'rounded-control', 'card' => 'rounded-card', 'pill' => 'rounded-full'] as $radius => $class)
                    <div class="grid size-20 place-items-center border border-line-strong bg-subtle text-xs text-ink-secondary {{ $class }}" dir="ltr">{{ $radius }}</div>
                @endforeach
                @foreach (['shadow-card' => 'XS', 'shadow-raised' => 'SM', 'shadow-pop' => 'MD', 'shadow-overlay' => 'LG', 'shadow-focus' => 'Focus'] as $shadow => $name)
                    <div class="grid size-20 place-items-center rounded-control bg-surface text-xs text-ink-secondary {{ $shadow }}" dir="ltr">{{ $name }}</div>
                @endforeach
            </div>
        </div>
    </x-ui::card>

    {{-- Icons --}}
    <x-ui::card id="icons" :title="$sections['icons']" :subtitle="trans_choice('platform::ui-kit.icons_count', count($icons), ['count' => count($icons)])">
        <ul class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-8 xl:grid-cols-12" role="list">
            @foreach ($icons as $icon)
                <li class="flex flex-col items-center gap-2 rounded-control p-3 text-ink hover:bg-subtle" title="{{ $icon }}">
                    <x-ui::icon :name="$icon" :size="24" />
                    <code class="max-w-full truncate text-[11px] text-ink-secondary" dir="ltr">{{ $icon }}</code>
                </li>
            @endforeach
        </ul>
    </x-ui::card>

    {{-- Buttons --}}
    <x-ui::card id="buttons" :title="$sections['buttons']">
        <div class="grid gap-5">
            @foreach (['primary', 'secondary', 'ghost', 'danger'] as $variant)
                <div class="flex flex-wrap items-center gap-3">
                    <span class="w-24 text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">{{ __('platform::ui-kit.variants.'.$variant) }}</span>
                    @foreach (['sm', 'md', 'lg'] as $size)
                        <x-ui::button :variant="$variant" :size="$size" icon="plus">{{ __('platform::ui-kit.button') }}</x-ui::button>
                    @endforeach
                    <x-ui::button :variant="$variant" disabled>{{ __('platform::ui-kit.disabled') }}</x-ui::button>
                    <x-ui::button :variant="$variant" loading>{{ __('platform::ui-kit.button') }}</x-ui::button>
                </div>
            @endforeach
            <div class="flex flex-wrap items-center gap-3">
                <span class="w-24 text-xs font-medium uppercase tracking-[0.06em] text-ink-muted">{{ __('platform::ui-kit.icon_buttons') }}</span>
                @foreach (['ghost', 'secondary', 'primary'] as $variant)
                    <x-ui::icon-button icon="more" :variant="$variant" size="sm" :label="__('platform::ui-kit.more_actions')" />
                    <x-ui::icon-button icon="more" :variant="$variant" :label="__('platform::ui-kit.more_actions')" />
                @endforeach
            </div>
        </div>
    </x-ui::card>

    {{-- Form fields (422 contract demo) --}}
    <x-ui::card id="forms" :title="$sections['forms']" :subtitle="__('platform::ui-kit.forms_hint')">
        <form method="POST" action="{{ route('admin.ui-kit.form') }}" data-ajax class="grid gap-6" novalidate>
            @csrf
            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <x-ui::input name="name" :label="__('platform::ui-kit.fields.product_name')" :placeholder="__('platform::ui-kit.fields.product_name_placeholder')" :helper="__('platform::ui-kit.fields.product_name_helper')" required />
                <x-ui::input name="email" type="email" icon="mail" :label="__('platform::ui-kit.fields.email')" required dir="ltr" />
                <x-ui::input name="disabled_field" :label="__('platform::ui-kit.fields.disabled')" :value="__('platform::ui-kit.fields.read_only_value')" disabled />
                <x-ui::input name="error_example" :label="__('platform::ui-kit.fields.with_error')" :error="__('platform::ui-kit.fields.required_message')" required />
                <x-ui::select name="category" :label="__('platform::ui-kit.fields.category')" :options="$categories" :placeholder="__('platform::ui-kit.fields.select_category')" :helper="__('platform::ui-kit.fields.searchable')" required />
                <x-ui::select name="countries" :label="__('platform::ui-kit.fields.countries')" :options="$countries" :selected="['sa', 'ae']" multiple :helper="__('platform::ui-kit.fields.countries_helper')" required />
                <x-ui::select name="disabled_select" :label="__('platform::ui-kit.fields.disabled')" :options="$categories" :placeholder="__('platform::ui-kit.fields.select_category')" disabled />
                <x-ui::phone name="phone" :label="__('platform::ui-kit.fields.phone')" :helper="__('platform::ui-kit.fields.phone_helper')" required />
                <x-ui::date name="start_date" :label="__('platform::ui-kit.fields.start_date')" required />
                <x-ui::daterange name="range" :label="__('platform::ui-kit.fields.date_range')" :helper="__('platform::ui-kit.fields.date_range_helper')" />
                <x-ui::textarea name="description" :label="__('platform::ui-kit.fields.description')" :placeholder="__('platform::ui-kit.fields.description_placeholder')" :maxlength="500" required class="md:col-span-2 xl:col-span-1" />
            </div>

            <x-ui::locale-tabs id="ui-kit-i18n">
                @foreach ($locales as $code => $language)
                    <x-ui::locale-pane parent="ui-kit-i18n" :locale="$code">
                        <x-ui::input :name="'title['.$code.']'" :label="__('platform::ui-kit.fields.title_in', ['language' => __('ui.languages.'.$code)])" required />
                    </x-ui::locale-pane>
                @endforeach
            </x-ui::locale-tabs>

            <x-ui::checkbox name="terms" :label="__('platform::ui-kit.fields.terms')" />

            <div class="flex flex-wrap justify-end gap-3 border-t border-line pt-5">
                <x-ui::button type="reset" variant="secondary">{{ __('platform::ui-kit.reset') }}</x-ui::button>
                <x-ui::button type="submit" icon="check">{{ __('platform::ui-kit.validate') }}</x-ui::button>
            </div>
        </form>
    </x-ui::card>

    {{-- Selection & upload --}}
    <x-ui::card id="selection" :title="$sections['selection']">
        <div class="grid gap-8">
            <div class="flex flex-wrap gap-6">
                <x-ui::checkbox name="demo_check_off" :label="__('platform::ui-kit.fields.featured')" />
                <x-ui::checkbox name="demo_check_on" :label="__('platform::ui-kit.fields.featured')" checked />
                <x-ui::checkbox name="demo_check_mixed" :label="__('platform::ui-kit.fields.featured')" indeterminate />
                <x-ui::checkbox name="demo_check_disabled" :label="__('platform::ui-kit.fields.featured')" disabled />
            </div>
            <x-ui::radio-group name="demo_radio" :label="__('platform::ui-kit.fields.option_group')" :options="['a' => __('platform::ui-kit.fields.option', ['n' => 1]), 'b' => __('platform::ui-kit.fields.option', ['n' => 2])]" value="b" />
            <div class="flex flex-wrap gap-6">
                <x-ui::switch name="demo_switch_on" :label="__('ui.status.active')" checked />
                <x-ui::switch name="demo_switch_off" :label="__('ui.status.active')" />
                <x-ui::switch name="demo_switch_disabled" :label="__('ui.status.active')" disabled />
            </div>
            <div class="grid gap-5 lg:grid-cols-2">
                <x-ui::file-upload name="attachments" :label="__('platform::ui-kit.fields.attachments')" accept=".pdf,.png,.jpg,.zip" multiple :hint="__('platform::ui-kit.fields.attachments_hint')" />
                <x-ui::file-upload name="attachment_error" :label="__('platform::ui-kit.fields.with_error')" :error="__('platform::ui-kit.fields.upload_error')" :hint="__('platform::ui-kit.fields.attachments_hint')" />
            </div>
            <x-ui::image-upload name="gallery" :label="__('platform::ui-kit.fields.images')" :max="8" :helper="__('platform::ui-kit.fields.images_helper')" />
        </div>
    </x-ui::card>

    {{-- Data display --}}
    <x-ui::card id="display" :title="$sections['display']">
        <div class="grid gap-8">
            <div class="grid gap-3">
                @foreach (['soft', 'outline', 'solid'] as $variant)
                    <div class="flex flex-wrap gap-2">
                        @foreach (['neutral', 'brand', 'success', 'warning', 'danger', 'info'] as $tone)
                            <x-ui::badge :tone="$tone" :variant="$variant">{{ __('platform::ui-kit.tones.'.$tone) }}</x-ui::badge>
                        @endforeach
                        <x-ui::badge tone="brand" variant="count">12</x-ui::badge>
                    </div>
                @endforeach
                <div class="flex flex-wrap gap-2">
                    @foreach (['draft', 'new', 'paid', 'pending', 'cancelled', 'shipped'] as $status)
                        <x-ui::status :status="$status" />
                    @endforeach
                </div>
            </div>

            <x-ui::tabs id="demo-tabs" param="demo_tab" :items="['gateways' => __('platform::ui-kit.tabs.gateways'), 'mail' => ['label' => __('platform::ui-kit.tabs.mail'), 'count' => 3], 'logs' => __('platform::ui-kit.tabs.logs'), 'disabled' => ['label' => __('platform::ui-kit.disabled'), 'disabled' => true]]">
                <x-ui::tab-panel tabs="demo-tabs" key="gateways" active class="pt-4 text-sm text-ink-secondary">{{ __('platform::ui-kit.tabs.panel', ['tab' => __('platform::ui-kit.tabs.gateways')]) }}</x-ui::tab-panel>
                <x-ui::tab-panel tabs="demo-tabs" key="mail" class="pt-4 text-sm text-ink-secondary">{{ __('platform::ui-kit.tabs.panel', ['tab' => __('platform::ui-kit.tabs.mail')]) }}</x-ui::tab-panel>
                <x-ui::tab-panel tabs="demo-tabs" key="logs" class="pt-4 text-sm text-ink-secondary">{{ __('platform::ui-kit.tabs.panel', ['tab' => __('platform::ui-kit.tabs.logs')]) }}</x-ui::tab-panel>
                <x-ui::tab-panel tabs="demo-tabs" key="disabled" />
            </x-ui::tabs>

            <x-ui::tabs id="demo-segmented" variant="segmented" param="" :items="['7d' => __('ui.chart.ranges.7d'), '30d' => __('ui.chart.ranges.30d'), '12m' => __('ui.chart.ranges.12m')]" active="30d">
                @foreach (['7d', '30d', '12m'] as $key)
                    <x-ui::tab-panel tabs="demo-segmented" :key="$key" :active="$key === '30d'" class="pt-3 text-sm text-ink-secondary">{{ __('platform::ui-kit.tabs.panel', ['tab' => __('ui.chart.ranges.'.$key)]) }}</x-ui::tab-panel>
                @endforeach
            </x-ui::tabs>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui::kpi-card :label="__('platform::ui-kit.sample.metric')" value="1,284" icon="wallet" :delta="12.4" :comparison="__('platform::ui-kit.sample.vs_last')" :spark="[3, 4, 3, 5, 6, 5, 8]" highlight />
                <x-ui::kpi-card :label="__('platform::ui-kit.sample.metric')" value="1,284" icon="cart" :delta="8.1" :comparison="__('platform::ui-kit.sample.vs_last')" :spark="[2, 3, 3, 5, 4, 6, 7]" />
                <x-ui::kpi-card :label="__('platform::ui-kit.sample.metric')" value="1,284" icon="return" :delta="-0.6" :comparison="__('platform::ui-kit.sample.vs_last')" :spark="[7, 6, 6, 5, 5, 4, 3]" />
                <x-ui::kpi-card :label="__('platform::ui-kit.sample.metric')" value="1,284" icon="chart" :delta="0" :comparison="__('platform::ui-kit.sample.vs_last')" :spark="[4, 4, 4, 4, 4, 4, 4]" />
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <x-ui::chart-card id="demo-line" :title="__('platform::ui-kit.sample.line_chart')" :subtitle="__('platform::ui-kit.sample.chart_subtitle')" type="line" :ranges="$lineRanges" range="30d" />
                <x-ui::chart-card id="demo-bar" :title="__('platform::ui-kit.sample.bar_chart')" :subtitle="__('platform::ui-kit.sample.chart_subtitle')" type="bar" :ranges="['7d' => ['labels' => $lineRanges['7d']['labels'], 'series' => [['label' => __('platform::ui-kit.sample.gross'), 'data' => [8, 6, 5, 4, 3, 2, 1], 'tone' => 'brand']]]]" />
                <x-ui::chart-card id="demo-donut" :title="__('platform::ui-kit.sample.donut_chart')" :subtitle="__('platform::ui-kit.sample.chart_subtitle')" type="doughnut" :height="180" total="100" :ranges="['30d' => ['labels' => [__('ui.status.delivered'), __('ui.status.shipped'), __('ui.status.processing'), __('ui.status.cancelled')], 'series' => [['label' => __('platform::ui-kit.sample.orders'), 'data' => [35, 27, 22, 16], 'tones' => ['success', 'info', 'warning', 'danger']]]]]" />
                <x-ui::chart-card id="demo-empty" :title="__('platform::ui-kit.sample.empty_chart')" type="line" empty />
            </div>
        </div>
    </x-ui::card>

    {{-- Feedback & page structure --}}
    <x-ui::card id="feedback" :title="$sections['feedback']">
        <div class="grid gap-8">
            <div class="grid gap-3 lg:grid-cols-2">
                <x-ui::toast tone="success" :title="__('platform::ui-kit.toasts.success_title')" :message="__('platform::ui-kit.toasts.success_message')" static class="pointer-events-auto" />
                <x-ui::toast tone="error" :title="__('platform::ui-kit.toasts.error_title')" :message="__('platform::ui-kit.toasts.error_message')" static class="pointer-events-auto" />
                <x-ui::toast tone="warning" :title="__('platform::ui-kit.toasts.warning_title')" :message="__('platform::ui-kit.toasts.warning_message')" static class="pointer-events-auto" />
                <x-ui::toast tone="info" :title="__('platform::ui-kit.toasts.info_title')" :message="__('platform::ui-kit.toasts.info_message')" static class="pointer-events-auto" />
            </div>
            <div class="flex flex-wrap gap-3">
                @foreach (['success', 'error', 'warning', 'info'] as $tone)
                    <x-ui::button variant="secondary" size="sm" :data-ui-kit-toast="$tone">{{ __('platform::ui-kit.toasts.fire', ['tone' => __('platform::ui-kit.tones.'.['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$tone])]) }}</x-ui::button>
                @endforeach
            </div>
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-card border border-line">
                    <x-ui::empty-state variant="no-data" :title="__('platform::ui-kit.empty.no_products')" :description="__('platform::ui-kit.empty.no_products_description')">
                        <x-ui::button icon="plus" :href="route('admin.ui-kit').'#forms'">{{ __('platform::ui-kit.empty.add_product') }}</x-ui::button>
                    </x-ui::empty-state>
                </div>
                <div class="rounded-card border border-line">
                    <x-ui::empty-state variant="no-results">
                        <x-ui::button variant="secondary" data-filters-clear="ui-kit-products">{{ __('ui.filters.clear') }}</x-ui::button>
                    </x-ui::empty-state>
                </div>
                <div class="rounded-card border border-line">
                    <x-ui::empty-state variant="error">
                        <x-ui::button variant="secondary" icon="refresh" :href="route('admin.ui-kit')">{{ __('ui.retry') }}</x-ui::button>
                    </x-ui::empty-state>
                </div>
            </div>
            <div class="grid gap-3">
                <x-ui::skeleton shape="circle" />
                <x-ui::skeleton :lines="3" class="max-w-md" />
                <x-ui::skeleton shape="rect" width="100%" height="80px" />
            </div>
            <div class="rounded-card border border-line p-5">
                <x-ui::page-header :title="__('platform::ui-kit.sample.order_title')" :subtitle="__('platform::ui-kit.sample.order_subtitle')" :breadcrumbs="[__('navigation.sections.sales') => null, __('navigation.admin.orders') => null, __('platform::ui-kit.sample.order_title') => null]">
                    <x-slot:status><x-ui::status status="processing" /></x-slot:status>
                    <x-slot:actions>
                        <x-ui::button variant="secondary" icon="printer" data-ui-kit-toast="info">{{ __('platform::ui-kit.sample.print_label') }}</x-ui::button>
                        <x-ui::button variant="danger" icon="x" :data-confirm="__('platform::ui-kit.confirm_cancel_message')" :data-confirm-title="__('platform::ui-kit.confirm_cancel_title')">{{ __('platform::ui-kit.sample.cancel_order') }}</x-ui::button>
                    </x-slot:actions>
                </x-ui::page-header>
            </div>
            <x-ui::pagination :paginator="$paginator" />
        </div>
    </x-ui::card>

    {{-- Overlays --}}
    <x-ui::card id="overlays" :title="$sections['overlays']" :subtitle="__('platform::ui-kit.overlays_hint')">
        <div class="flex flex-wrap items-start gap-3">
            <x-ui::button data-overlay-open="demo-modal" icon="plus">{{ __('platform::ui-kit.open_modal') }}</x-ui::button>
            <x-ui::button variant="secondary" data-overlay-open="demo-drawer" icon="filter">{{ __('platform::ui-kit.open_drawer') }}</x-ui::button>
            <x-ui::button variant="danger" icon="trash" :data-confirm="__('platform::ui-kit.confirm_delete_message')" :data-confirm-title="__('platform::ui-kit.confirm_delete_many')" data-ui-kit-toast="success">{{ __('platform::ui-kit.open_confirm') }}</x-ui::button>
            <x-ui::button variant="secondary" icon="alert-triangle" :data-confirm="__('platform::ui-kit.confirm_type_message')" :data-confirm-title="__('platform::ui-kit.confirm_type_title')" data-confirm-require="Megz Store" data-ui-kit-toast="success">{{ __('platform::ui-kit.open_confirm_typed') }}</x-ui::button>

            <x-ui::dropdown align="start" :width="200" :label="__('platform::ui-kit.dropdown')">
                <x-slot:trigger>
                    <x-ui::button variant="secondary" icon-trailing="chevron-down" data-dropdown-trigger aria-haspopup="menu" aria-expanded="false">{{ __('platform::ui-kit.dropdown') }}</x-ui::button>
                </x-slot:trigger>
                <x-ui::dropdown-item icon="eye" :href="route('admin.ui-kit')">{{ __('platform::ui-kit.actions.view') }}</x-ui::dropdown-item>
                <x-ui::dropdown-item icon="edit" data-overlay-open="demo-modal">{{ __('platform::ui-kit.actions.edit') }}</x-ui::dropdown-item>
                <x-ui::dropdown-item icon="copy" data-ui-kit-toast="info">{{ __('platform::ui-kit.actions.duplicate') }}</x-ui::dropdown-item>
                <x-ui::dropdown-divider />
                <x-ui::dropdown-item icon="trash" tone="danger" :data-confirm="__('platform::ui-kit.confirm_delete_message')" data-ui-kit-toast="success">{{ __('ui.delete') }}</x-ui::dropdown-item>
            </x-ui::dropdown>

            <x-ui::notification-dropdown :items="$notifications" />
            <x-ui::notification-dropdown :items="[]" />
            <x-ui::user-menu :user="auth('admin')->user()" />
        </div>
    </x-ui::card>

    <x-ui::modal id="demo-modal" :title="__('platform::ui-kit.modal.title')" :subtitle="__('platform::ui-kit.modal.subtitle')" close-on-success>
        <form id="demo-modal-form" method="POST" action="{{ route('admin.ui-kit.form') }}" data-ajax class="grid gap-5" novalidate>
            @csrf
            <x-ui::input name="name" id="modal-name" :label="__('platform::ui-kit.modal.zone_name')" required data-autofocus />
            <x-ui::input name="email" id="modal-email" type="email" :label="__('platform::ui-kit.fields.email')" required dir="ltr" />
        </form>
        <x-slot:footer>
            <x-ui::button variant="secondary" data-overlay-close>{{ __('ui.cancel') }}</x-ui::button>
            <x-ui::button type="submit" form="demo-modal-form">{{ __('platform::ui-kit.modal.save') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::modal>

    <x-ui::drawer id="demo-drawer" :title="__('ui.filters.title')" :subtitle="__('platform::ui-kit.drawer_subtitle')">
        <div class="grid gap-5">
            <x-ui::select name="drawer_status" :label="__('platform::ui-kit.fields.status')" :options="$statusOptions" multiple />
            <x-ui::select name="drawer_country" :label="__('platform::ui-kit.fields.countries')" :options="$countries" :placeholder="__('platform::ui-kit.fields.all')" />
            <x-ui::daterange name="drawer_range" :label="__('platform::ui-kit.fields.date_range')" />
        </div>
        <x-slot:footer>
            <x-ui::button variant="ghost" data-overlay-close>{{ __('ui.filters.reset') }}</x-ui::button>
            <x-ui::button data-overlay-close>{{ __('ui.filters.apply') }}</x-ui::button>
        </x-slot:footer>
    </x-ui::drawer>

    {{-- Data table --}}
    <section id="table" class="grid gap-3" aria-labelledby="table-title">
        <h2 id="table-title" class="text-base font-semibold text-ink">{{ $sections['table'] }}</h2>
        <x-ui::datatable
            id="ui-kit-products"
            :url="route('admin.ui-kit.table')"
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
                <x-ui::filters-bar target="ui-kit-products" :search-placeholder="__('platform::ui-kit.table.search')" :columns="[__('platform::ui-kit.table.product'), __('platform::ui-kit.table.category'), __('platform::ui-kit.table.price'), __('platform::ui-kit.table.stock'), __('platform::ui-kit.table.status')]">
                    <x-ui::select name="status" :label="__('platform::ui-kit.fields.status')" label-hidden :options="$statusOptions" :placeholder="__('platform::ui-kit.fields.any_status')" :search="false" allow-clear data-filter :data-filter-label="__('platform::ui-kit.fields.status')" class="w-44" />
                </x-ui::filters-bar>
            </x-slot:filters>
            <x-slot:bulk>
                <x-ui::button variant="ghost" size="sm" icon="check-circle" data-bulk-action :data-url="route('admin.ui-kit.bulk')" data-method="POST">{{ __('platform::ui-kit.table.activate') }}</x-ui::button>
                <x-ui::button variant="ghost" size="sm" icon="download" data-bulk-action :data-url="route('admin.ui-kit.bulk')" data-method="POST">{{ __('platform::ui-kit.table.export') }}</x-ui::button>
                <x-ui::button variant="danger" size="sm" icon="trash" data-bulk-action :data-url="route('admin.ui-kit.bulk')" data-method="POST" :data-confirm="__('platform::ui-kit.confirm_delete_message')" :data-confirm-title="__('platform::ui-kit.confirm_delete_many')">{{ __('ui.delete') }}</x-ui::button>
            </x-slot:bulk>
        </x-ui::datatable>
    </section>
</x-layouts.panel>
