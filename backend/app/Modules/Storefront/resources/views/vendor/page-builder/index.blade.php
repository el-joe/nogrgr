{{--
    Vendor › Your store › Page builder (VEN-032, decision #117 b; Figma Vendor Panel (new
    screens) 200:33486 aligned to Tenant Panel, vendor kit #101). Left: the sections of the
    store home (theme library, admin default order) or of a custom store page — drag to
    reorder, switch on / off, open a section for its settings (title / subtitle per store
    language, banners, product source, item limit, layout options, show in countries); every
    change autosaves the draft. Right: live signed preview of the DRAFT (desktop / mobile),
    reloaded after each save. Publish keeps a version (history below: restore into the
    draft); discard the draft; reset to the theme default.
--}}
@php
    $title = __('content::page_builder.title');
@endphp
<x-vendor::layout :title="$title" page="vendor-page-builder">
    @if ($target === null)
        <div class="grid gap-6">
            <x-ui::page-header :title="$title" :subtitle="__('content::page_builder.subtitle')" :breadcrumbs="[__('navigation.vendor.store_design') => null, $title => null]" />
            <x-ui::card>
                <x-ui::empty-state icon="palette" :title="__('content::page_builder.empty.title')" :description="__('content::page_builder.empty.description')" />
            </x-ui::card>
        </div>
    @else
        @php
            $home = $target->isHome();
            $state = $layout === null || ($layout->published === null && $layout->draft === null) || $stale ? 'default' : ($layout->hasUnpublishedChanges() ? 'draft' : 'published');
            $tones = ['default' => 'neutral', 'draft' => 'warning', 'published' => 'success'];
            $editing = $home ? $target->theme?->name : ($target->page?->title ?: $target->page?->slug);
        @endphp
        <div class="grid gap-6" data-page-builder
             data-draft-url="{{ vendor_route('store.page-builder.draft', ['target' => $target->key]) }}"
             data-preview-url="{{ vendor_route('store.page-builder.preview', ['target' => $target->key]) }}"
             data-state="{{ $state }}"
             @unless ($canManage) data-readonly @endunless>
            <x-ui::page-header :title="$title" :subtitle="$home ? __('content::page_builder.subtitle_home', ['theme' => $editing]) : __('content::page_builder.subtitle_page', ['page' => $editing])" :breadcrumbs="[__('navigation.vendor.store_design') => null, $title => null]">
                <x-slot:actions>
                    @if (count($targets) > 1)
                        <x-ui::select name="builder_target" :label="__('content::page_builder.editing')" label-hidden :options="$targets" :selected="$currentUrl" class="w-full sm:w-72" data-builder-target />
                    @endif
                    @if ($canManage)
                        <x-ui::button variant="ghost" icon="refresh"
                            :data-confirm="$home ? __('content::page_builder.reset_message', ['theme' => $editing]) : __('content::page_builder.reset_page_message')"
                            :data-confirm-title="__('content::page_builder.reset_title')"
                            data-confirm-tone="warning"
                            :data-confirm-label="__('content::page_builder.reset')"
                            data-confirm-method="POST"
                            :data-confirm-url="vendor_route('store.page-builder.reset', ['target' => $target->key])">{{ $home ? __('content::page_builder.reset') : __('content::page_builder.reset_page') }}</x-ui::button>
                        @if ($layout?->draft !== null && $layout?->published !== null && ! $stale)
                            <x-ui::button variant="secondary" icon="x"
                                :data-confirm="__('content::page_builder.discard_message')"
                                :data-confirm-title="__('content::page_builder.discard_title')"
                                data-confirm-tone="danger"
                                :data-confirm-label="__('content::page_builder.discard')"
                                data-confirm-method="DELETE"
                                :data-confirm-url="vendor_route('store.page-builder.discard', ['target' => $target->key])">{{ __('content::page_builder.discard') }}</x-ui::button>
                        @endif
                        <x-ui::button icon="check"
                            :data-confirm="__('content::page_builder.publish_message')"
                            :data-confirm-title="__('content::page_builder.publish_title')"
                            data-confirm-tone="primary"
                            :data-confirm-label="__('content::page_builder.publish')"
                            data-confirm-method="POST"
                            :data-confirm-url="vendor_route('store.page-builder.publish', ['target' => $target->key])"
                            data-builder-publish>{{ __('content::page_builder.publish') }}</x-ui::button>
                    @endif
                </x-slot:actions>
            </x-ui::page-header>

            @if ($stale)
                <p class="flex items-start gap-2 rounded-control bg-warning-bg px-3 py-2 text-sm text-ink" role="note"><x-ui::icon name="alert-triangle" :size="18" class="shrink-0 text-warning" />{{ __('content::page_builder.stale_note', ['theme' => $editing]) }}</p>
            @endif
            @unless ($canManage)
                <p class="flex items-start gap-2 rounded-control bg-info-bg px-3 py-2 text-sm text-ink" role="note"><x-ui::icon name="info" :size="18" class="shrink-0 text-info" />{{ __('content::page_builder.readonly_note') }}</p>
            @endunless

            <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,440px)_minmax(0,1fr)]">
                <div class="grid gap-6">
                    <x-ui::card :title="__('content::page_builder.sections_title')" :subtitle="$home ? __('content::page_builder.sections_help') : __('content::page_builder.page_sections_help')" id="page-builder-sections">
                        <x-slot:actions>
                            <x-ui::badge :tone="$tones[$state]" data-builder-state data-labels="{{ json_encode(['default' => __('content::page_builder.state.default'), 'draft' => __('content::page_builder.state.draft'), 'published' => __('content::page_builder.state.published'), 'saving' => __('content::page_builder.state.saving'), 'error' => __('content::page_builder.state.error')]) }}" role="status"><span data-state-label>{{ __('content::page_builder.state.'.$state) }}</span></x-ui::badge>
                        </x-slot:actions>
                        <form id="page-builder-form" method="POST" action="{{ vendor_route('store.page-builder.draft', ['target' => $target->key]) }}" novalidate class="grid gap-4" data-builder-form>
                            @csrf
                            @method('PUT')
                            <ol class="grid gap-2" data-section-list>
                                @foreach ($sections as $index => $section)
                                    @include('storefront::vendor.page-builder._section', ['section' => $section, 'home' => $home, 'uid' => (string) $section['key'], 'position' => $index + 1])
                                @endforeach
                            </ol>
                            @unless ($home)
                                <div @class(['grid justify-items-center gap-2 rounded-control border border-dashed border-line px-4 py-8 text-center', 'hidden' => $sections !== []]) data-sections-placeholder>
                                    <x-ui::icon name="layers" :size="28" class="text-ink-muted" />
                                    <p class="text-sm font-medium text-ink">{{ __('content::page_builder.page_empty_title') }}</p>
                                    <p class="max-w-xs text-xs text-ink-muted">{{ __('content::page_builder.page_empty') }}</p>
                                </div>
                                @if ($canManage)
                                    <div class="grid gap-2 border-t border-line pt-4">
                                        <span class="text-[13px] font-medium text-ink">{{ __('content::page_builder.add_section') }}</span>
                                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach ($pageTypes as $pageType)
                                                <button type="button" class="flex items-center gap-2 border border-line bg-surface px-3 py-2 text-start text-sm text-ink transition-colors hover:border-brand hover:text-brand" data-section-add="{{ $pageType['type']->value }}">
                                                    <x-ui::icon :name="$pageType['type']->icon()" :size="16" class="shrink-0" />
                                                    <span class="min-w-0 truncate">{{ $pageType['type']->label() }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                        @foreach ($pageTypes as $pageType)
                                            <template data-section-template="{{ $pageType['type']->value }}">
                                                @include('storefront::vendor.page-builder._section', ['section' => ['key' => '__KEY__', 'type' => $pageType['type']->value, 'label' => $pageType['type']->label(), 'icon' => $pageType['type']->icon(), 'schema' => $pageType['schema'], 'settings' => [], 'countries' => [], 'enabled' => true], 'home' => false, 'uid' => '__KEY__', 'position' => 0])
                                            </template>
                                        @endforeach
                                    </div>
                                @endif
                            @endunless
                            <p class="text-xs text-ink-muted" data-builder-saved>{{ $layout?->published_at ? __('content::page_builder.last_published', ['date' => $layout->published_at->locale(app()->getLocale())->isoFormat('D MMM YYYY, HH:mm')]) : __('content::page_builder.never_published') }}</p>
                        </form>
                    </x-ui::card>

                    <x-ui::card :title="__('content::page_builder.versions_title')" :subtitle="__('content::page_builder.versions_help')" id="page-builder-versions">
                        @include('storefront::vendor.page-builder._versions')
                    </x-ui::card>
                </div>

                <x-ui::card :title="__('content::page_builder.preview_title')" :subtitle="__('content::page_builder.preview_help')" class="xl:sticky xl:top-20" :padding="false">
                    <x-slot:actions>
                        <div class="flex items-center gap-2">
                            <div class="inline-flex bg-subtle p-1" role="group" aria-label="{{ __('content::page_builder.device') }}">
                                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-ink-muted aria-pressed:bg-surface aria-pressed:text-ink" aria-pressed="true" data-preview-device="desktop">{{ __('content::page_builder.desktop') }}</button>
                                <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm text-ink-muted aria-pressed:bg-surface aria-pressed:text-ink" aria-pressed="false" data-preview-device="mobile">{{ __('content::page_builder.mobile') }}</button>
                            </div>
                            <button type="button" class="grid size-9 place-items-center bg-subtle text-ink-muted hover:text-ink" data-preview-reload title="{{ __('content::page_builder.reload') }}" aria-label="{{ __('content::page_builder.reload') }}"><x-ui::icon name="refresh" :size="16" /></button>
                            <a href="#" target="_blank" rel="noopener" class="grid size-9 place-items-center bg-subtle text-ink-muted hover:text-ink" data-preview-open title="{{ __('content::page_builder.open_tab') }}" aria-label="{{ __('content::page_builder.open_tab') }}"><x-ui::icon name="eye" :size="16" /></a>
                        </div>
                    </x-slot:actions>
                    <div class="relative grid min-h-[480px] place-items-start justify-items-center overflow-hidden bg-subtle p-3 md:min-h-[720px]" data-preview-stage>
                        <div class="relative h-[720px] w-full max-w-full overflow-hidden border border-line bg-white transition-[width] duration-300 ease-out" data-preview-frame-wrap>
                            <iframe title="{{ __('content::page_builder.preview_title') }}" class="absolute start-0 top-0 max-w-none origin-top-left rtl:origin-top-right" loading="lazy" data-preview-frame></iframe>
                            <div class="absolute inset-0 grid place-items-center bg-surface/80" data-preview-loading>
                                <span class="flex items-center gap-2 text-sm text-ink-muted"><x-ui::spinner />{{ __('content::page_builder.preview_loading') }}</span>
                            </div>
                            <div class="absolute inset-0 hidden place-items-center bg-surface p-6 text-center" data-preview-error>
                                <div class="grid justify-items-center gap-3">
                                    <x-ui::icon name="alert-triangle" :size="28" class="text-warning" />
                                    <p class="text-sm text-ink">{{ __('content::page_builder.preview_error') }}</p>
                                    <x-ui::button size="sm" variant="secondary" icon="refresh" data-preview-retry>{{ __('content::page_builder.retry') }}</x-ui::button>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui::card>
            </div>
        </div>
    @endif
</x-vendor::layout>
