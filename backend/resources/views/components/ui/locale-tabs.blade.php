{{--
    Locale tabs (Figma 28:10380): segmented control, one tab per enabled language.
    A tab whose pane holds an invalid field shows a red dot and opens on submit.
    <x-ui::locale-tabs id="product-i18n">
        @foreach (\App\Shared\Support\Locale::enabled() as $code => $language)
            <x-ui::locale-pane parent="product-i18n" :locale="$code"> … </x-ui::locale-pane>
        @endforeach
    </x-ui::locale-tabs>
--}}
@props(['id', 'locales' => null, 'active' => null])
@php
    $locales ??= \App\Shared\Support\Locale::enabled();
    $active ??= array_key_first($locales);
@endphp
<div data-tabs data-tabs-param="" {{ $attributes->class(['grid gap-4']) }}>
    <div role="tablist" aria-label="{{ __('ui.locale_tabs') }}" class="inline-flex w-fit gap-1 rounded-control bg-subtle p-1">
        @foreach ($locales as $code => $language)
            <button
                type="button"
                role="tab"
                id="{{ $id }}-{{ $code }}-tab"
                aria-controls="{{ $id }}-{{ $code }}"
                aria-selected="{{ $code === $active ? 'true' : 'false' }}"
                tabindex="{{ $code === $active ? 0 : -1 }}"
                data-tab-key="{{ $code }}"
                class="group inline-flex h-8 items-center gap-1.5 rounded-chip px-3 text-[13px] text-ink-secondary transition-colors hover:text-ink aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-card"
            >
                <span class="text-xs font-medium uppercase group-aria-selected:text-ink-brand">{{ $code }}</span>
                <span>{{ __('ui.languages.'.$code) !== 'ui.languages.'.$code ? __('ui.languages.'.$code) : $language['name'] }}</span>
                <span class="hidden size-1.5 rounded-full bg-danger group-data-error:inline-block" role="img" aria-label="{{ __('ui.has_errors') }}"></span>
            </button>
        @endforeach
    </div>
    {{ $slot }}
</div>
