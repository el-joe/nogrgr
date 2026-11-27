{{--
    Admin › Content › Storefront › Themes (ADM-086, Figma Admin Panel 78:76835; decisions
    #119 / #120): the 13 catalogue themes as cards (preview object-contain, name, status
    pill, default badge, countries, template parts) with actions — edit (ADM-086a modal),
    countries modal, home sections (ADM-087), template parts (ADM-088), activate /
    deactivate and set default with confirmation — then the stores' approved custom themes.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('storefront.themes.manage');
    $canParts = (bool) auth('admin')->user()?->can('storefront.template-parts.view');
    $canReview = Route::has('admin.storefront.theme-reviews.index') && (bool) auth('admin')->user()?->can('storefront.theme-reviews.view');
@endphp
<x-layouts.panel surface="admin" :title="__('content::admin_themes.title')" data-page="admin-themes">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('content::admin_themes.title')"
            :subtitle="__('content::admin_themes.subtitle')"
            :breadcrumbs="[__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('navigation.admin.themes') => null]"
        >
            <x-slot:actions>
                <x-ui::button variant="secondary" icon="layers" :href="route('admin.storefront.home-sections.index')">{{ __('content::admin_themes.home_sections') }}</x-ui::button>
                @if ($canParts)
                    <x-ui::button variant="secondary" icon="grid" :href="route('admin.storefront.template-parts.index')">{{ __('content::admin_themes.template_parts') }}</x-ui::button>
                @endif
            </x-slot:actions>
        </x-ui::page-header>

        @if ($themes->isEmpty())
            <x-ui::card>
                <x-ui::empty-state icon="palette" :title="__('content::admin_themes.empty.title')" :description="__('content::admin_themes.empty.description')" />
            </x-ui::card>
        @else
            <p class="text-sm text-ink-secondary">{{ trans_choice('content::admin_themes.count', $themes->count(), ['count' => $themes->count()]) }}</p>
            <ul class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" role="list" data-theme-list>
                @foreach ($themes as $theme)
                    @include('storefront::admin.themes._card', ['theme' => $theme, 'canManage' => $canManage, 'canParts' => $canParts])
                @endforeach
            </ul>
        @endif

        <x-ui::card :title="__('content::admin_themes.custom.title')" :subtitle="__('content::admin_themes.custom.subtitle')" id="custom-themes" data-custom-themes>
            @if ($canReview)
                <x-slot:actions>
                    <x-ui::button variant="secondary" size="sm" icon="shield" :href="route('admin.storefront.theme-reviews.index')">{{ __('content::admin_themes.custom.review_queue') }}</x-ui::button>
                </x-slot:actions>
            @endif
            @if ($customThemes->isEmpty())
                <x-ui::empty-state compact icon="palette" :description="__('content::admin_themes.custom.empty')" />
            @else
                <ul class="divide-y divide-line" role="list">
                    @foreach ($customThemes as $custom)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-ink" dir="auto">{{ $custom->name }}</p>
                                <p class="mt-0.5 text-xs text-ink-muted">
                                    {{ __('content::admin_themes.custom.store') }}: <span dir="auto">{{ $custom->tenant?->getAttribute('name') ?? '#'.$custom->tenant_id }}</span>
                                    @if ($custom->liveVersion)
                                        · <span dir="ltr">{{ __('content::admin_themes.custom.version', ['version' => $custom->liveVersion->version]) }}</span>
                                    @endif
                                </p>
                            </div>
                            <x-ui::status :status="$custom->is_active ? 'active' : 'inactive'">{{ $custom->is_active ? __('content::admin_themes.custom.live') : __('content::admin_themes.custom.off') }}</x-ui::status>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui::card>
    </div>

    @if ($canManage)
        <x-ui::modal id="theme-modal" :title="__('content::admin_themes.edit')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('content::admin_themes.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="theme-form" icon="check" data-remote-submit>{{ __('content::admin_themes.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
        <x-ui::modal id="theme-countries-modal" :title="__('content::admin_themes.countries')">
            @include('commerce::admin.partials.remote-states')
            <x-slot:footer>
                <x-ui::button variant="secondary" data-overlay-close>{{ __('content::admin_themes.cancel') }}</x-ui::button>
                <x-ui::button type="submit" form="theme-countries-form" icon="check" data-remote-submit>{{ __('content::admin_themes.save') }}</x-ui::button>
            </x-slot:footer>
        </x-ui::modal>
    @endif
</x-layouts.panel>
