{{--
    One section row of the page builder (VEN-032): drag handle, position, name, on / off,
    settings panel (schema fields + "Show in countries"), remove (store pages only).
    $section (key, label, schema, settings, countries, enabled; home: style_label, page: type
    + icon), $home, $uid (form key, "__KEY__" in templates), $canManage, $countries, $locales,
    $options, $position.
--}}
@php
    $prefix = 'sections['.$uid.']';
    $settings = (array) ($section['settings'] ?? []);
    $selectedCountries = array_map('intval', (array) ($section['countries'] ?? []));
    $panelId = 'pb-panel-'.$uid;
    $hasSchema = ($section['schema'] ?? []) !== [];
    $customised = $settings !== [] || $selectedCountries !== [];
@endphp
<li class="rounded-control border border-line bg-surface transition-shadow" data-section-item data-key="{{ $uid }}">
    <input type="hidden" name="{{ $prefix }}[key]" value="{{ $home ? $section['key'] : $uid }}">
    @unless ($home)
        <input type="hidden" name="{{ $prefix }}[type]" value="{{ $section['type'] }}">
    @endunless
    <div class="flex items-center gap-2 px-2 py-2.5 sm:gap-3 sm:px-3">
        @if ($canManage)
            <button type="button" class="grid size-8 shrink-0 cursor-grab touch-none place-items-center rounded-control text-ink-muted hover:bg-subtle hover:text-ink active:cursor-grabbing" data-drag-handle aria-label="{{ __('content::page_builder.drag', ['section' => $section['label']]) }}" title="{{ __('content::page_builder.drag', ['section' => $section['label']]) }}">
                <x-ui::icon name="menu" :size="16" />
            </button>
        @endif
        <span class="w-5 shrink-0 text-center text-xs text-ink-muted" dir="ltr" data-sort-position>{{ $position }}</span>
        @unless ($home)
            <x-ui::icon :name="$section['icon']" :size="18" class="shrink-0 text-ink-muted" />
        @endunless
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-ink">{{ $section['label'] }}</p>
            <p class="flex flex-wrap items-center gap-x-2 text-xs text-ink-muted">
                @if (! empty($section['style_label']))<span class="truncate capitalize">{{ $section['style_label'] }}</span>@endif
                <span @class(['text-brand', 'hidden' => ! $customised]) data-customised>{{ __('content::page_builder.customised') }}</span>
            </p>
        </div>
        <x-ui::switch :name="$prefix.'[enabled]'" :id="'pb-enabled-'.$uid" :checked="(bool) ($section['enabled'] ?? true)" :disabled="! $canManage" :aria-label="__('content::page_builder.show', ['section' => $section['label']])" data-section-toggle />
        <button type="button" class="grid size-8 shrink-0 place-items-center rounded-control text-ink-muted hover:bg-subtle hover:text-ink" aria-expanded="false" aria-controls="{{ $panelId }}" data-section-expand title="{{ __('content::page_builder.settings_for', ['section' => $section['label']]) }}" aria-label="{{ __('content::page_builder.settings_for', ['section' => $section['label']]) }}">
            <x-ui::icon name="chevron-down" :size="16" class="transition-transform" />
        </button>
    </div>
    <div id="{{ $panelId }}" class="hidden border-t border-line px-3 pb-4 pt-3" data-section-panel>
        <div class="grid gap-4">
            @foreach ($section['schema'] ?? [] as $field)
                @include('storefront::vendor.page-builder._field', ['field' => $field, 'prefix' => $prefix.'[settings]', 'settings' => $settings])
            @endforeach
            @unless ($hasSchema)
                <p class="text-xs text-ink-muted">{{ __('content::page_builder.no_settings') }}</p>
            @endunless
            @if ($countries !== [])
                <x-ui::select :name="$prefix.'[countries]'" :id="'pb-countries-'.$uid" multiple :label="__('content::page_builder.fields.countries')" :options="$countries" :selected="$selectedCountries" :placeholder="__('content::page_builder.all_countries')" :helper="__('content::page_builder.countries_help')" :disabled="! $canManage" />
            @endif
            @if (! $home && $canManage)
                <x-ui::button type="button" variant="ghost" size="sm" icon="trash" class="w-fit text-danger" data-section-remove>{{ __('content::page_builder.remove_section') }}</x-ui::button>
            @endif
        </div>
    </div>
</li>
