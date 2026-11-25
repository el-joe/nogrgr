{{--
    One option of the repeater. $index is a number, or "__INDEX__" in the <template>
    (replaced by catalog.js). Locked options (used by variants) cannot be removed.
--}}
@php
    $base = 'options['.$index.']';
    $isLocked = $option !== null && in_array($option->id, $locked, true);
@endphp
<li data-sortable-item data-option-row class="grid gap-3 rounded-control border border-line bg-surface p-3 sm:grid-cols-[auto_auto_minmax(0,1fr)_auto] sm:items-start">
    <button type="button" data-drag-handle class="grid size-8 cursor-grab place-items-center rounded-chip text-ink-muted hover:bg-subtle hover:text-ink focus-visible:outline-2 focus-visible:outline-primary" aria-label="{{ __('catalog::common.drag_handle', ['name' => $option?->name ?? __('catalog::attributes.fields.option_name')]) }}" title="{{ __('catalog::common.drag_hint') }}">
        <x-ui::icon name="menu" :size="16" />
    </button>
    <span data-position class="hidden w-5 pt-2 text-center text-xs text-ink-secondary sm:block" dir="ltr">{{ is_int($index) ? $index + 1 : '' }}</span>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @if ($option?->id)
            <input type="hidden" name="{{ $base }}[id]" value="{{ $option->id }}">
        @endif
        @foreach ($locales as $code => $language)
            <x-ui::input :name="$base.'[name]['.$code.']'" :label="__('catalog::attributes.fields.option_name').' ('.strtoupper($code).')'" :value="$option?->getTranslation('name', $code, false)" :required="in_array($code, \App\Modules\Catalog\Support\CatalogLocales::REQUIRED, true)" maxlength="80" :dir="$language['dir']" :lang="$code" />
        @endforeach
        <div data-swatch-field @if (! $swatch) hidden @endif class="grid gap-1.5">
            <x-ui::input :name="$base.'[swatch]'" :label="__('catalog::attributes.fields.swatch')" :value="$option?->swatch" placeholder="#121212" maxlength="7" dir="ltr" data-swatch-input>
                <x-slot:suffix><input type="color" data-swatch-picker value="{{ $option?->swatch ?? '#000000' }}" class="size-7 cursor-pointer rounded-chip border border-line bg-transparent" aria-label="{{ __('catalog::attributes.fields.swatch') }}" tabindex="-1"></x-slot:suffix>
            </x-ui::input>
        </div>
    </div>
    <div class="flex items-center gap-2 sm:pt-7">
        <x-ui::switch :name="$base.'[is_active]'" :checked="$option === null || $option->is_active" :aria-label="__('catalog::attributes.fields.option_active')" />
        @if ($isLocked)
            <span class="grid size-8 place-items-center text-ink-muted" title="{{ __('catalog::attributes.option_locked') }}"><x-ui::icon name="key" :size="16" :label="__('catalog::attributes.option_locked')" /></span>
        @else
            <x-ui::icon-button icon="trash" size="sm" :label="__('catalog::attributes.remove_option')" data-option-remove />
        @endif
    </div>
</li>
