{{--
    Admin › Storefront › Home sections (ADM-087, Figma Admin Panel 78:78508; old
    HomeVariantsPage): pick a theme, then switch its home sections on / off and drag them
    into order; Save stores the theme's default home layout (the page builder starts
    from it), Reset goes back to the design order with every section on.
--}}
@php
    $canManage = (bool) auth('admin')->user()?->can('storefront.themes.manage');
    $options = $themes->mapWithKeys(fn ($item) => [route('admin.storefront.home-sections.index', ['theme' => $item->id]) => (string) $item->name])->all();
    $enabledCount = count(array_filter($sections, fn (array $section): bool => $section['enabled']));
@endphp
<x-layouts.panel surface="admin" :title="__('content::admin_themes.sections.title')" data-page="admin-themes">
    <div class="grid gap-6">
        <x-ui::page-header
            :title="__('content::admin_themes.sections.title')"
            :subtitle="__('content::admin_themes.sections.subtitle')"
            :breadcrumbs="[__('navigation.sections.content') => null, __('navigation.admin.storefront') => null, __('navigation.admin.themes') => route('admin.storefront.themes.index'), __('content::admin_themes.sections.title') => null]"
        >
            @if ($theme)
                <x-slot:actions>
                    <x-ui::select name="theme_picker" :label="__('content::admin_themes.sections.theme')" label-hidden :options="$options" :selected="route('admin.storefront.home-sections.index', ['theme' => $theme->id])" class="w-64" data-theme-picker />
                </x-slot:actions>
            @endif
        </x-ui::page-header>

        @if ($theme === null)
            <x-ui::card>
                <x-ui::empty-state icon="palette" :title="__('content::admin_themes.empty.title')" :description="__('content::admin_themes.empty.description')" />
            </x-ui::card>
        @else
            @unless ($theme->is_active)
                <p class="flex items-start gap-2 rounded-control bg-warning-bg px-3 py-2 text-sm text-ink" role="note"><x-ui::icon name="alert-triangle" :size="18" class="shrink-0 text-warning" />{{ __('content::admin_themes.sections.inactive_note') }}</p>
            @endunless
            <x-ui::card :title="__('content::admin_themes.sections.list_title', ['theme' => $theme->name])" :subtitle="__('content::admin_themes.sections.list_help')" id="theme-sections">
                <x-slot:actions>
                    <div class="flex items-center gap-2">
                        <x-ui::badge :tone="$theme->home_sections ? 'brand' : 'neutral'">{{ $theme->home_sections ? __('content::admin_themes.sections.customised') : __('content::admin_themes.sections.design_order') }}</x-ui::badge>
                        <span class="text-xs text-ink-muted" data-sections-count data-template="{{ __('content::admin_themes.sections.enabled_count', ['enabled' => ':enabled', 'total' => count($sections)]) }}">{{ __('content::admin_themes.sections.enabled_count', ['enabled' => $enabledCount, 'total' => count($sections)]) }}</span>
                    </div>
                </x-slot:actions>
                <form id="theme-sections-form" method="POST" action="{{ route('admin.storefront.home-sections.update', $theme) }}" data-ajax novalidate class="grid gap-4" data-theme-sections @unless ($canManage) data-readonly @endunless>
                    @csrf
                    @method('PUT')
                    <ol class="grid gap-2" data-section-list>
                        @foreach ($sections as $index => $section)
                            <li class="flex items-center gap-3 rounded-control border border-line bg-surface px-3 py-2.5" data-section-item data-key="{{ $section['key'] }}">
                                <input type="hidden" name="order[]" value="{{ $section['key'] }}">
                                @if ($canManage)
                                    <button type="button" class="grid size-8 shrink-0 cursor-grab place-items-center rounded-control text-ink-muted hover:bg-subtle hover:text-ink active:cursor-grabbing" data-drag-handle aria-label="{{ __('content::admin_themes.sections.drag', ['section' => $section['label']]) }}" title="{{ __('content::admin_themes.sections.drag', ['section' => $section['label']]) }}">
                                        <x-ui::icon name="menu" :size="16" />
                                    </button>
                                @endif
                                <span class="w-5 shrink-0 text-center text-xs text-ink-muted" dir="ltr" data-sort-position>{{ $index + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-ink">{{ $section['label'] }}</p>
                                    @if ($section['style_label'])
                                        <p class="truncate text-xs capitalize text-ink-muted">{{ $section['style_label'] }}</p>
                                    @endif
                                </div>
                                <x-ui::switch name="enabled[]" :id="'section-'.$theme->id.'-'.$index" :value="$section['key']" :unchecked-value="null" :checked="$section['enabled']" :disabled="! $canManage" :aria-label="__('content::admin_themes.sections.show', ['section' => $section['label']])" data-section-toggle />
                            </li>
                        @endforeach
                    </ol>
                    @if ($canManage)
                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-line pt-4">
                            @if ($theme->home_sections)
                                <x-ui::button variant="ghost" icon="refresh" :data-confirm="__('content::admin_themes.sections.reset_message')" :data-confirm-title="__('content::admin_themes.sections.reset_title', ['theme' => $theme->name])" data-confirm-tone="primary" :data-confirm-label="__('content::admin_themes.sections.reset')" data-confirm-method="DELETE" :data-confirm-url="route('admin.storefront.home-sections.reset', $theme)">{{ __('content::admin_themes.sections.reset') }}</x-ui::button>
                            @endif
                            <x-ui::button type="submit" icon="check">{{ __('content::admin_themes.save') }}</x-ui::button>
                        </div>
                    @endif
                </form>
            </x-ui::card>
        @endif
    </div>
</x-layouts.panel>
